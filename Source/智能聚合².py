import os, sys, re, hashlib, inspect, importlib.util, json, base64, threading
from urllib.parse import urlparse, parse_qs, urlencode, urlunparse
import requests
from base.spider import Spider


class Spider(Spider):
    PATH_1 = "/storage/emulated/0/Film-TV/File/py/Hunter"
    PATH_2 = "F:\模拟共享\Film-TV\File\py\Hunter"
    CACHE_DIR_NAME = ".spider_cache"
    MAX_CACHE_SIZE = 30
    FAST_PLACEHOLDER = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"

    def init(self, extend):
        cfg = {}
        if isinstance(extend, str):
            try:
                cfg = json.loads(extend)
            except:
                cfg = {}
        elif isinstance(extend, dict):
            cfg = extend
        
        self.scan_paths = []
        if os.path.exists(self.PATH_1):
            self.scan_paths.append(self.PATH_1)
        target_path2 = self.PATH_2 if self.PATH_2 else os.path.dirname(os.path.abspath(__file__))
        if os.path.exists(target_path2) and target_path2 not in self.scan_paths:
            self.scan_paths.append(target_path2)
        main_path = self.scan_paths[0] if self.scan_paths else "."
        self.cache_dir = os.path.join(main_path, self.CACHE_DIR_NAME)
        self.class_cache = {}
        self.spider_cache = {}
        self.spider_classes_cache = {}
        self.cache_mtime = {}
        
        self.global_lock = threading.Lock()
        cfg['hls_proxy'] = False
        self.extend_config = json.dumps(cfg)
        
        try:
            if not os.path.exists(self.cache_dir):
                os.makedirs(self.cache_dir)
            self.SELF_NAME = os.path.basename(inspect.getfile(inspect.currentframe()))
        except:
            pass
        self._clean_orphan_cache()
        self.session = requests.Session()

    def getName(self):
        return "智能聚合"

    def _get_placeholder(self, seed=None):
        return self.FAST_PLACEHOLDER

    def _normalize_vod(self, v, py_path, spider=None):
        vid = v.get('vod_id') or v.get('id')
        if vid:
            v['vod_id'] = f"{py_path}|{vid}"
        if not v.get('vod_name'):
            v['vod_name'] = v.get('title') or v.get('name') or "未命名"
        if not v.get('vod_remarks'):
            v['vod_remarks'] = v.get('remark') or ""
        if not v.get('vod_pic'):
            v['vod_pic'] = self.FAST_PLACEHOLDER
        return v

    def _load_spider_instance(self, py_path):
        if not os.path.exists(py_path) or os.path.basename(py_path) == self.SELF_NAME:
            return None, "文件不存在"
        if py_path in self.spider_cache and self.cache_mtime.get(py_path) == os.path.getmtime(py_path):
            return self.spider_cache[py_path], "OK"
        mod_name = f"m{hashlib.md5(py_path.encode()).hexdigest()[:8]}"
        if mod_name in sys.modules:
            del sys.modules[mod_name]
        try:
            spec = importlib.util.spec_from_file_location(mod_name, py_path)
            mod = importlib.util.module_from_spec(spec)
            sys.modules[mod_name] = mod
            spec.loader.exec_module(mod)
            candidates = [getattr(mod, n) for n in dir(mod) if isinstance(getattr(mod, n), type) and getattr(getattr(mod, n), '__module__') == mod_name and hasattr(getattr(mod, n), 'homeContent')]
            if not candidates:
                return None, "无爬虫类"
            cls = candidates[0] if len(candidates)==1 else [c for c in candidates if c.__name__!='Spider'][0]
            self.spider_classes_cache[py_path] = cls
            instance = cls()
            if hasattr(instance, 'init'):
                try:
                    instance.init(self.extend_config) if len(inspect.signature(instance.init).parameters) else instance.init()
                except:
                    pass
            self.spider_cache[py_path] = instance
            self.cache_mtime[py_path] = os.path.getmtime(py_path)
            return instance, "OK"
        except:
            return None, "加载失败"

    def _clean_orphan_cache(self):
        if not self.cache_dir or not os.path.exists(self.cache_dir):
            return
        valid_hashes = set()
        try:
            for p in self.scan_paths:
                if not os.path.exists(p):
                    continue
                for f in os.listdir(p):
                    if f.endswith(".py") and not f.startswith("__"):
                        full_path = os.path.join(p, f)
                        valid_hashes.add(hashlib.md5(full_path.encode()).hexdigest()+".json")
            for f in os.listdir(self.cache_dir):
                if f.endswith(".json") and f not in valid_hashes:
                    os.remove(os.path.join(self.cache_dir, f))
        except:
            pass

    def _get_classes(self, py_path):
        if py_path in self.class_cache and self.class_cache[py_path][0] == os.path.getmtime(py_path):
            return self.class_cache[py_path][1]
        cache_file = os.path.join(self.cache_dir, f"{hashlib.md5(py_path.encode()).hexdigest()}.json")
        if os.path.exists(cache_file):
            try:
                with open(cache_file) as f:
                    d = json.load(f)
                if d.get('mtime') == os.path.getmtime(py_path):
                    self.class_cache[py_path] = (d['mtime'], d['classes'])
                    return d['classes']
            except:
                pass
        spider, _ = self._load_spider_instance(py_path)
        if spider:
            try:
                res = spider.homeContent({})
                if res and 'class' in res:
                    classes = res['class']
                    self.class_cache[py_path] = (os.path.getmtime(py_path), classes)
                    try:
                        with open(cache_file, 'w') as f:
                            json.dump({'mtime': os.path.getmtime(py_path), 'classes': classes}, f)
                    except:
                        pass
                    return classes
            except:
                pass
        return [{'type_id': 'auto', 'type_name': '默认'}]

    def homeContent(self, filter):
        classes, filters = [], {}
        for path in self.scan_paths:
            if not os.path.exists(path):
                continue
            files = [f for f in os.listdir(path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
            for f in files:
                full_path = os.path.join(path, f)
                display_name = f.replace(".py", "")
                classes.append({"type_id": full_path, "type_name": display_name})
                filters[full_path] = [{"key": "sub", "name": "分类", "value": [{"n": i['type_name'], "v": i['type_id']} for i in self._get_classes(full_path)]}]
        return {"class": classes, "filters": filters}

    def categoryContent(self, tid, pg, filter, extend):
        with self.global_lock:
            try:
                spider, _ = self._load_spider_instance(tid)
                if not spider:
                    return {"list": []}
                sub_tid = (extend.get("sub") if extend else None) or self._get_classes(tid)[0].get('type_id')
                if not sub_tid:
                    return {"list": []}
                res = spider.categoryContent(sub_tid, pg, filter, extend)
                if not res or 'list' not in res:
                    return {"list": []}
                for v in res['list']:
                    self._normalize_vod(v, tid, spider)
                return res
            except:
                return {"list": []}

    def detailContent(self, array):
        with self.global_lock:
            if not array or "|" not in array[0]:
                return {"list": []}
            py_path, real_id = array[0].split("|", 1)
            try:
                spider, _ = self._load_spider_instance(py_path)
                if not spider:
                    return {"list": []}
                res = spider.detailContent([real_id])
                if res and 'list' in res:
                    vod = res['list'][0]
                    # 修改播放地址中的ID为py_path|real_id格式
                    if vod.get('vod_play_url'):
                        new_play_urls = []
                        for play in vod['vod_play_url'].split('#'):
                            if '$' in play:
                                title, pid = play.split('$', 1)
                                # 提取真实ID（去掉@后缀）
                                if '@' in pid:
                                    real_pid = pid.split('@')[0]
                                else:
                                    real_pid = pid
                                new_play_urls.append(f"{title}${py_path}|{real_pid}")
                            else:
                                new_play_urls.append(f"{py_path}|{play}")
                        vod['vod_play_url'] = '#'.join(new_play_urls)
                    if not vod.get('vod_id'):
                        vod['vod_id'] = f"{py_path}|{real_id}"
                    if not vod.get('vod_name'):
                        vod['vod_name'] = vod.get('title') or real_id
                    if not vod.get('vod_pic'):
                        vod['vod_pic'] = self.FAST_PLACEHOLDER
                    return {"list": [vod]}
            except:
                pass
            return {"list": []}

    def playerContent(self, flag, id, vipFlags):
        with self.global_lock:
            if "|" not in id:
                return {"parse": 0, "url": "error"}
            py_path, real_id = id.split("|", 1)
            try:
                spider, _ = self._load_spider_instance(py_path)
                if not spider:
                    return {"parse": 0, "url": "error"}
                # 直接调用子插件
                result = spider.playerContent(flag, real_id, vipFlags)
                return result
            except:
                pass
            return {"parse": 0, "url": ""}

    def searchContent(self, key, quick, pg="1"):
        if not key:
            return {"list": []}
        res_list = []
        all_files = []
        for path in self.scan_paths:
            if not os.path.exists(path):
                continue
            all_files.extend([os.path.join(path, f) for f in os.listdir(path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME])
        
        def fetch_search(py_path):
            with self.global_lock:
                try:
                    spider, _ = self._load_spider_instance(py_path)
                    if spider and hasattr(spider, 'searchContent'):
                        res = spider.searchContent(key, quick, pg)
                        if res and 'list' in res:
                            list_data = [self._normalize_vod(v, py_path, spider) for v in res['list']]
                            return list_data
                except:
                    pass
                return []
        
        for py_path in all_files:
            res_list.extend(fetch_search(py_path))
        return {"list": res_list[:100]}

    def localProxy(self, params):
        # 解析请求参数，判断是哪个子插件的代理请求
        if params.get('do') != 'py':
            return None
        
        # 从params中获取vid参数
        vid = params.get('vid')
        if not vid:
            return None
        
        # 尝试从缓存中查找对应的插件路径
        # 这里使用一个简单的映射：遍历所有已加载的插件，检查是否能处理这个vid
        with self.global_lock:
            for py_path, spider in self.spider_cache.items():
                if spider and hasattr(spider, 'localProxy'):
                    try:
                        # 尝试调用子插件的localProxy
                        result = spider.localProxy(params)
                        if result is not None:
                            return result
                    except:
                        continue
        
        # 如果找不到对应的插件，直接返回None
        return None

    def destroy(self):
        try:
            self.session.close()
            for inst in self.spider_cache.values():
                if hasattr(inst, 'destroy'):
                    try:
                        inst.destroy()
                    except:
                        pass
        except:
            pass