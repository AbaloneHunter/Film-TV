import sys
sys.dont_write_bytecode = True

import os
import re
import hashlib
import inspect
import importlib.util
import json
import threading
from copy import deepcopy
from concurrent.futures import ThreadPoolExecutor, as_completed
from collections import OrderedDict
from urllib.parse import urlparse, parse_qs, urlencode, urlunparse
import requests
from base.spider import Spider


class Spider(Spider):
    CACHE_DIR_NAME = ".spider_cache"
    MAX_CACHE_SIZE = 30
    FAST_PLACEHOLDER = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
    ID_SEP = "|||"

    def __init__(self):
        super().__init__()
        self.scan_paths = []
        self.cache_dir = None
        self.class_cache = {}
        self.spider_cache = OrderedDict()
        self.spider_base_url = {}
        self.global_lock = threading.RLock()
        self.executor = ThreadPoolExecutor(max_workers=5)
        self.session = requests.Session()
        self.session.timeout = (3, 10)
        self.SELF_NAME = None
        self.extend_config = {}
        self._initialized = False

    def init(self, extend):
        cfg = {}
        if isinstance(extend, str):
            try:
                cfg = json.loads(extend)
            except:
                cfg = {}
        elif isinstance(extend, dict):
            cfg = extend
        cfg.setdefault('hls_proxy', False)
        self.extend_config = cfg

        # ---------- 确定扫描基准目录 ----------
        # 1. 若用户通过 extend 明确指定 scan_base，则直接使用
        if 'scan_base' in cfg and isinstance(cfg['scan_base'], str):
            base_dir = cfg['scan_base']
            if not os.path.exists(base_dir):
                # 如果目录不存在，尝试在当前工作目录下寻找相对路径
                base_dir = os.path.join(os.getcwd(), base_dir)
            if os.path.exists(base_dir) and os.path.isdir(base_dir):
                self.scan_paths = [base_dir]
            else:
                # 无效则回退自动检测
                self.scan_paths = []
        else:
            self.scan_paths = []

        # 2. 自动检测（如果没有通过配置指定或指定无效）
        if not self.scan_paths:
            try:
                # 获取当前文件的真实路径（比 __file__ 更可靠）
                current_file = inspect.getfile(inspect.currentframe())
                if os.path.isfile(current_file) and not current_file.startswith('<'):
                    base_dir = os.path.dirname(os.path.abspath(current_file))
                    self.scan_paths = [base_dir]
                    self.SELF_NAME = os.path.basename(current_file)
                else:
                    # 如果获取不到，则使用当前工作目录
                    base_dir = os.getcwd()
                    self.scan_paths = [base_dir]
                    # 自身文件名使用一个虚拟名称，但实际不会匹配，因为文件不在列表中
                    self.SELF_NAME = '聚合.py'
            except:
                # 兜底
                base_dir = os.getcwd()
                self.scan_paths = [base_dir]
                self.SELF_NAME = '聚合.py'

        # 如果 scan_paths 包含多个（但我们只用一个），可以扩展为列表，现在保留兼容
        # 但为了支持多目录，我们可以接受 'scan_paths' 列表（可后续添加）
        # 这里我们只取第一个作为主要扫描目录，但也可支持多个
        # 扩展：如果用户传入 scan_paths 列表，则使用它
        if 'scan_paths' in cfg and isinstance(cfg['scan_paths'], list):
            paths = [p for p in cfg['scan_paths'] if os.path.exists(p)]
            if paths:
                self.scan_paths = paths
                # 注意：此时 SELF_NAME 可能无法确定，但我们会跳过自身
                # 我们仍然尝试从当前文件获取名称
                try:
                    current_file = inspect.getfile(inspect.currentframe())
                    if os.path.isfile(current_file):
                        self.SELF_NAME = os.path.basename(current_file)
                except:
                    pass

        # 确保 SELF_NAME 非空
        if not self.SELF_NAME:
            self.SELF_NAME = '聚合.py'  # 占位，实际扫描时会跳过同名文件

        # 打印调试信息（部署后可删除）
        print(f"[聚合] 扫描目录: {self.scan_paths}")
        print(f"[聚合] 自身文件名（将跳过）: {self.SELF_NAME}")

        # ---------- 缓存目录 ----------
        # 使用第一个扫描路径作为缓存根目录
        cache_root = self.scan_paths[0] if self.scan_paths else os.getcwd()
        self.cache_dir = os.path.join(cache_root, self.CACHE_DIR_NAME)
        try:
            if not os.path.exists(self.cache_dir):
                os.makedirs(self.cache_dir)
        except Exception as e:
            print(f"[聚合] 创建缓存目录失败: {e}")
            # 无法创建则使用系统临时目录
            import tempfile
            self.cache_dir = os.path.join(tempfile.gettempdir(), self.CACHE_DIR_NAME)
            if not os.path.exists(self.cache_dir):
                os.makedirs(self.cache_dir, exist_ok=True)

        if not self._initialized:
            self._clean_orphan_cache()
            self._initialized = True

    # ---------- 以下方法不变（与之前完全相同） ----------
    def getName(self):
        return "智能聚合"

    def _get_placeholder(self, seed=None):
        return self.FAST_PLACEHOLDER

    def _normalize_vod(self, v, py_path, spider=None):
        vid = v.get('vod_id') or v.get('id')
        if vid:
            v['vod_id'] = f"{py_path}{self.ID_SEP}{vid}"
        else:
            v['vod_id'] = f"{py_path}{self.ID_SEP}{v.get('title', 'unknown')}"

        if not v.get('vod_name'):
            v['vod_name'] = v.get('title') or v.get('name') or "未命名"

        if not v.get('vod_remarks'):
            v['vod_remarks'] = v.get('remark') or ""

        pic = None
        for key in ['vod_pic', 'pic', 'img', 'cover', 'thumb', 'thumbnail', 'poster', 'image']:
            val = v.get(key)
            if val and isinstance(val, str) and val.strip():
                pic = val.strip()
                if pic.lower() in ('null', 'none', 'undefined', 'false'):
                    pic = None
                    continue
                if pic.startswith('//'):
                    pic = 'https:' + pic
                elif not pic.startswith(('http://', 'https://', 'data:')):
                    base = None
                    if spider:
                        base = getattr(spider, 'base_url', None) or getattr(spider, 'host', None) or getattr(spider, 'api', None)
                    if base:
                        if not base.endswith('/'):
                            base += '/'
                        if pic.startswith('/'):
                            pic = base + pic[1:]
                        else:
                            pic = base + pic
                break

        if pic:
            v['vod_pic'] = pic
        else:
            v['vod_pic'] = self.FAST_PLACEHOLDER

        return v

    def _load_spider_instance(self, py_path):
        with self.global_lock:
            if py_path in self.spider_cache:
                inst, mtime = self.spider_cache[py_path]
                if mtime == os.path.getmtime(py_path):
                    self.spider_cache.move_to_end(py_path)
                    return inst, "OK"

            mod_name = f"m{hashlib.md5(py_path.encode()).hexdigest()[:8]}"
            if mod_name in sys.modules:
                del sys.modules[mod_name]

            try:
                spec = importlib.util.spec_from_file_location(mod_name, py_path)
                mod = importlib.util.module_from_spec(spec)
                sys.modules[mod_name] = mod
                spec.loader.exec_module(mod)

                candidates = []
                for name in dir(mod):
                    obj = getattr(mod, name)
                    if (isinstance(obj, type) and
                        hasattr(obj, 'homeContent') and
                        obj.__module__ == mod_name):
                        candidates.append(obj)

                if not candidates:
                    return None, "无爬虫类"

                cls = candidates[0] if len(candidates) == 1 else next(
                    (c for c in candidates if c.__name__ != 'Spider'), candidates[0]
                )

                instance = cls()
                if hasattr(instance, 'init'):
                    sig = inspect.signature(instance.init)
                    if len(sig.parameters) >= 1:
                        instance.init(json.dumps(self.extend_config))
                    else:
                        instance.init()

                base = getattr(instance, 'base_url', None) or getattr(instance, 'host', None) or getattr(instance, 'api', None)
                if base:
                    self.spider_base_url[py_path] = base

                self.spider_cache[py_path] = (instance, os.path.getmtime(py_path))
                self.spider_cache.move_to_end(py_path)
                if len(self.spider_cache) > self.MAX_CACHE_SIZE:
                    oldest = next(iter(self.spider_cache))
                    del self.spider_cache[oldest]
                    if oldest in self.spider_base_url:
                        del self.spider_base_url[oldest]

                return instance, "OK"

            except Exception as e:
                print(f"[聚合] 加载 {py_path} 失败: {e}")
                return None, "加载失败"

    def _clean_orphan_cache(self):
        with self.global_lock:
            if not self.cache_dir or not os.path.exists(self.cache_dir):
                return
            valid_hashes = set()
            for p in self.scan_paths:
                if not os.path.exists(p):
                    continue
                for f in os.listdir(p):
                    if f.endswith(".py") and not f.startswith("__"):
                        full_path = os.path.join(p, f)
                        valid_hashes.add(hashlib.md5(full_path.encode()).hexdigest() + ".json")
            for f in os.listdir(self.cache_dir):
                if f.endswith(".json") and f not in valid_hashes:
                    try:
                        os.remove(os.path.join(self.cache_dir, f))
                    except:
                        pass

    def _get_classes(self, py_path):
        with self.global_lock:
            if py_path in self.class_cache:
                mtime, classes = self.class_cache[py_path]
                if mtime == os.path.getmtime(py_path):
                    return classes

            cache_file = os.path.join(self.cache_dir, hashlib.md5(py_path.encode()).hexdigest() + ".json")
            if os.path.exists(cache_file):
                try:
                    with open(cache_file, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    if data.get('mtime') == os.path.getmtime(py_path):
                        self.class_cache[py_path] = (data['mtime'], data['classes'])
                        return data['classes']
                except:
                    pass

            spider, status = self._load_spider_instance(py_path)
            if spider:
                try:
                    res = spider.homeContent({})
                    if res and 'class' in res:
                        classes = res['class']
                        self.class_cache[py_path] = (os.path.getmtime(py_path), classes)
                        try:
                            with open(cache_file, 'w', encoding='utf-8') as f:
                                json.dump({'mtime': os.path.getmtime(py_path), 'classes': classes}, f)
                        except:
                            pass
                        return classes
                except:
                    pass

            default = [{'type_id': 'auto', 'type_name': '默认'}]
            self.class_cache[py_path] = (os.path.getmtime(py_path), default)
            return default

    def homeContent(self, filter):
        with self.global_lock:
            classes = []
            filters = {}
            for path in self.scan_paths:
                if not os.path.exists(path):
                    continue
                files = [f for f in os.listdir(path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
                for f in files:
                    full_path = os.path.join(path, f)
                    display_name = f.replace(".py", "")
                    classes.append({"type_id": full_path, "type_name": display_name})
                    sub_classes = self._get_classes(full_path)
                    filters[full_path] = [{
                        "key": "sub",
                        "name": "分类",
                        "value": [{"n": item['type_name'], "v": item['type_id']} for item in sub_classes]
                    }]
            return {"class": classes, "filters": filters}

    def categoryContent(self, tid, pg, filter, extend):
        with self.global_lock:
            try:
                spider, status = self._load_spider_instance(tid)
                if not spider:
                    return {"list": []}

                if not extend or 'sub' not in extend:
                    sub_classes = self._get_classes(tid)
                    sub_tid = sub_classes[0]['type_id'] if sub_classes else 'auto'
                else:
                    sub_tid = extend['sub']

                ext_copy = deepcopy(extend) if extend else {}
                res = spider.categoryContent(sub_tid, pg, filter, ext_copy)

                if not res or 'list' not in res:
                    return {"list": []}

                for v in res['list']:
                    self._normalize_vod(v, tid, spider)
                return res

            except Exception as e:
                print(f"[聚合] categoryContent 错误: {e}")
                return {"list": []}

    def detailContent(self, array):
        with self.global_lock:
            if not array or self.ID_SEP not in array[0]:
                return {"list": []}

            py_path, real_id = array[0].split(self.ID_SEP, 1)
            try:
                spider, status = self._load_spider_instance(py_path)
                if not spider:
                    return {"list": []}

                res = spider.detailContent([real_id])
                if res and 'list' in res:
                    vod = res['list'][0]
                    self._normalize_vod(vod, py_path, spider)

                    if vod.get('vod_play_url'):
                        play_parts = []
                        for part in vod['vod_play_url'].split('#'):
                            if '$' in part:
                                title, pid = part.split('$', 1)
                                if not pid.startswith(f"{py_path}{self.ID_SEP}"):
                                    play_parts.append(f"{title}${py_path}{self.ID_SEP}{pid}")
                                else:
                                    play_parts.append(part)
                            else:
                                if not part.startswith(f"{py_path}{self.ID_SEP}"):
                                    play_parts.append(f"{py_path}{self.ID_SEP}{part}")
                                else:
                                    play_parts.append(part)
                        vod['vod_play_url'] = '#'.join(play_parts)

                    return {"list": [vod]}
            except Exception as e:
                print(f"[聚合] detailContent 错误: {e}")
            return {"list": []}

    def playerContent(self, flag, id, vipFlags):
        with self.global_lock:
            if self.ID_SEP not in id:
                return {"parse": 0, "url": "error"}
            py_path, real_id = id.split(self.ID_SEP, 1)
            try:
                spider, status = self._load_spider_instance(py_path)
                if not spider:
                    return {"parse": 0, "url": "error"}
                return spider.playerContent(flag, real_id, vipFlags)
            except Exception as e:
                print(f"[聚合] playerContent 错误: {e}")
                return {"parse": 0, "url": ""}

    def _safe_search(self, py_path, key, quick, pg):
        try:
            spider, status = self._load_spider_instance(py_path)
            if spider and hasattr(spider, 'searchContent'):
                res = spider.searchContent(key, quick, pg)
                if res and 'list' in res:
                    return [self._normalize_vod(v, py_path, spider) for v in res['list']]
        except Exception as e:
            print(f"[聚合] 搜索 {py_path} 错误: {e}")
        return []

    def searchContent(self, key, quick, pg="1"):
        if not key:
            return {"list": []}

        all_files = []
        for path in self.scan_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME:
                    all_files.append(os.path.join(path, f))

        results = []
        futures = []
        with self.executor as executor:
            for py_path in all_files:
                futures.append(executor.submit(self._safe_search, py_path, key, quick, pg))
            for future in as_completed(futures, timeout=10):
                try:
                    data = future.result(timeout=2)
                    if data:
                        results.extend(data)
                except:
                    pass
        seen = set()
        unique = []
        for v in results:
            vid = v.get('vod_id')
            if vid and vid not in seen:
                seen.add(vid)
                unique.append(v)
        return {"list": unique[:100]}

    def localProxy(self, params):
        if params.get('do') != 'py':
            return None
        vid = params.get('vid')
        if not vid:
            return None
        with self.global_lock:
            for py_path, (spider, _) in self.spider_cache.items():
                if spider and hasattr(spider, 'localProxy'):
                    try:
                        result = spider.localProxy(params)
                        if result is not None:
                            return result
                    except:
                        continue
        return None

    def destroy(self):
        try:
            self.session.close()
            for py_path, (inst, _) in self.spider_cache.items():
                if hasattr(inst, 'destroy'):
                    try:
                        inst.destroy()
                    except:
                        pass
            self.spider_cache.clear()
            self.class_cache.clear()
            self.spider_base_url.clear()
            self.executor.shutdown(wait=False)
        except:
            pass