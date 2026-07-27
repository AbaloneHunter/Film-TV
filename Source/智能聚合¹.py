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
    # 硬编码扫描路径
    PATH_1 = "/storage/emulated/0/Film-TV/File/py/Abalone"
    PATH_2 = "F:\\模拟共享\\Film-TV\\File\\py\\Abalone"

    CACHE_DIR_NAME = ".spider_cache"
    MAX_CACHE_SIZE = 30
    FAST_PLACEHOLDER = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
    ID_SEP = "|||"  # 自定义分隔符，避免冲突

    def __init__(self):
        super().__init__()
        self.scan_paths = []
        self.cache_dir = None
        self.class_cache = {}
        self.spider_cache = OrderedDict()      # {py_path: (instance, mtime)}
        self.spider_base_url = {}              # {py_path: base_url}
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

        self.scan_paths = []
        if os.path.exists(self.PATH_1):
            self.scan_paths.append(self.PATH_1)
        if os.path.exists(self.PATH_2) and self.PATH_2 not in self.scan_paths:
            self.scan_paths.append(self.PATH_2)
        if not self.scan_paths:
            self.scan_paths.append(os.path.dirname(os.path.abspath(__file__)))

        main_path = self.scan_paths[0] if self.scan_paths else "."
        self.cache_dir = os.path.join(main_path, self.CACHE_DIR_NAME)
        try:
            if not os.path.exists(self.cache_dir):
                os.makedirs(self.cache_dir)
            self.SELF_NAME = os.path.basename(inspect.getfile(inspect.currentframe()))
        except:
            pass

        if not self._initialized:
            self._clean_orphan_cache()
            self._initialized = True

    def getName(self):
        return "智能聚合"

    def _get_placeholder(self, seed=None):
        return self.FAST_PLACEHOLDER

    def _normalize_vod(self, v, py_path, spider=None):
        # ID
        vid = v.get('vod_id') or v.get('id')
        if vid:
            v['vod_id'] = f"{py_path}{self.ID_SEP}{vid}"
        else:
            v['vod_id'] = f"{py_path}{self.ID_SEP}{v.get('title', 'unknown')}"

        # 标题
        if not v.get('vod_name'):
            v['vod_name'] = v.get('title') or v.get('name') or "未命名"

        # 备注
        if not v.get('vod_remarks'):
            v['vod_remarks'] = v.get('remark') or ""

        # 图片处理
        pic = None
        for key in ['vod_pic', 'pic', 'img', 'cover', 'thumb', 'thumbnail', 'poster', 'image']:
            val = v.get(key)
            if val and isinstance(val, str) and val.strip():
                pic = val.strip()
                # 排除无效
                if pic.lower() in ('null', 'none', 'undefined', 'false'):
                    pic = None
                    continue
                # 补全协议
                if pic.startswith('//'):
                    pic = 'https:' + pic
                # 相对路径补全
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

                # 保存基础URL供图片补全
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

            except:
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

            except:
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

                    # 处理播放URL，添加前缀但保留原始ID完整
                    if vod.get('vod_play_url'):
                        play_parts = []
                        for part in vod['vod_play_url'].split('#'):
                            if '$' in part:
                                title, pid = part.split('$', 1)
                                # 如果尚未添加前缀则添加
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
            except:
                pass
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
            except:
                return {"parse": 0, "url": ""}

    def _safe_search(self, py_path, key, quick, pg):
        try:
            spider, status = self._load_spider_instance(py_path)
            if spider and hasattr(spider, 'searchContent'):
                res = spider.searchContent(key, quick, pg)
                if res and 'list' in res:
                    return [self._normalize_vod(v, py_path, spider) for v in res['list']]
        except:
            pass
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
        # 去重
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