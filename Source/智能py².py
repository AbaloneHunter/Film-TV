# -*- coding: utf-8 -*-
import os
import sys
import re
import hashlib
import inspect
import importlib.util
import json
import base64
from concurrent.futures import ThreadPoolExecutor, as_completed
from base.spider import Spider
class Spider(Spider):
    SCAN_PATH = "/storage/emulated/0/Film-TV/File/py/Hunter"
    CACHE_DIR_NAME = ".spider_cache"
    MAX_CACHE_SIZE = 30
    def init(self, extend):
        self.extend_config = extend or {}
        self.scan_path = extend.get('path', self.SCAN_PATH) if extend else self.SCAN_PATH
        self.class_cache = {}
        self.spider_cache = {}
        self.cache_mtime = {}
        self.cache_dir = os.path.join(self.scan_path, self.CACHE_DIR_NAME)
        try:
            if not os.path.exists(self.cache_dir): os.makedirs(self.cache_dir)
            self.SELF_NAME = os.path.basename(inspect.getfile(inspect.currentframe()))
        except: self.cache_dir = None
    def getName(self): return "智能聚合(极速版)"
    # --- 核心工具：数据清洗 ---
    def _normalize_vod(self, v, py_path, spider=None, process_img=True):
        vid = v.get('vod_id') or v.get('id')
        if vid: v['vod_id'] = f"{py_path}|{vid}"
        if not v.get('vod_name'): v['vod_name'] = v.get('title') or v.get('name') or "未命名"
        pic = v.get('vod_pic') or v.get('pic') or v.get('img')
        if process_img and pic and ('url=' in pic or 'getProxyUrl' in pic):
            processed = self._process_proxy_image(pic, spider)
            v['vod_pic'] = processed if processed else self._get_placeholder(vid)
        elif not pic:
            v['vod_pic'] = self._get_placeholder(vid)            
        if not v.get('vod_remarks'): v['vod_remarks'] = v.get('remark') or ""
        return v
    def _get_placeholder(self, seed):
        return f"https://picsum.photos/seed/{hashlib.md5(str(seed).encode()).hexdigest()[:8]}/300/450.jpg"
    def _process_proxy_image(self, pic_url, spider):
        if not spider: return None
        try:
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(pic_url)
            params = parse_qs(parsed.query)
            if 'url' in params and hasattr(spider, 'localProxy'):
                res = spider.localProxy({'url': params['url'][0]})
                if res and len(res) == 3 and res[0] == 200:
                    return f"data:{res[1]};base64,{base64.b64encode(res[2]).decode()}"
        except: pass
        return None
    # --- 加载与缓存 ---
    def _load_spider_instance(self, py_path):
        if not os.path.exists(py_path) or os.path.basename(py_path) == self.SELF_NAME: return None, "文件不存在"
        if py_path in self.spider_cache and self.cache_mtime.get(py_path) == os.path.getmtime(py_path):
            return self.spider_cache[py_path], "OK"
        mod_name = f"m{hashlib.md5(py_path.encode()).hexdigest()[:8]}"
        if mod_name in sys.modules: del sys.modules[mod_name]
        try:
            spec = importlib.util.spec_from_file_location(mod_name, py_path)
            mod = importlib.util.module_from_spec(spec)
            sys.modules[mod_name] = mod
            spec.loader.exec_module(mod)
            candidates = [getattr(mod, n) for n in dir(mod) 
                          if isinstance(getattr(mod, n), type) 
                          and getattr(getattr(mod, n), '__module__') == mod_name 
                          and hasattr(getattr(mod, n), 'homeContent')]            
            if not candidates: return None, "无爬虫类"
            cls = candidates[0] if len(candidates)==1 else [c for c in candidates if c.__name__!='Spider'][0]
            instance = cls()
            if hasattr(instance, 'init'):
                try: instance.init(self.extend_config) if len(inspect.signature(instance.init).parameters) else instance.init()
                except: pass
            self.spider_cache[py_path] = instance
            self.cache_mtime[py_path] = os.path.getmtime(py_path)
            return instance, "OK"
        except: return None, "加载失败"
    def _get_classes(self, py_path):
        if py_path in self.class_cache and self.class_cache[py_path][0] == os.path.getmtime(py_path):
            return self.class_cache[py_path][1]
        cache_file = os.path.join(self.cache_dir, f"{hashlib.md5(py_path.encode()).hexdigest()}.json")
        if os.path.exists(cache_file):
            try:
                with open(cache_file) as f: d = json.load(f)
                if d.get('mtime') == os.path.getmtime(py_path):
                    self.class_cache[py_path] = (d['mtime'], d['classes'])
                    return d['classes']
            except: pass
        spider, _ = self._load_spider_instance(py_path)
        if spider:
            try:
                res = spider.homeContent({})
                if res and 'class' in res:
                    classes = res['class']
                    self.class_cache[py_path] = (os.path.getmtime(py_path), classes)
                    try:
                        with open(cache_file, 'w') as f: json.dump({'mtime': os.path.getmtime(py_path), 'classes': classes}, f)
                    except: pass
                    return classes
            except: pass
        return [{'type_id': 'auto', 'type_name': '默认'}]
    # --- 标准接口 ---
    def homeContent(self, filter):
        if not os.path.exists(self.scan_path): return {"class": []}
        files = [f for f in os.listdir(self.scan_path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
        classes = [{"type_id": os.path.join(self.scan_path, f), "type_name": f.replace(".py","")} for f in files]
        filters = {}
        for c in classes:
            filters[c["type_id"]] = [{"key": "sub", "name": "分类", "value": [{"n": i['type_name'], "v": i['type_id']} for i in self._get_classes(c["type_id"])]}]
        return {"class": classes, "filters": filters}
    def categoryContent(self, tid, pg, filter, extend):
        spider, _ = self._load_spider_instance(tid)
        if not spider: return {"list": []}
        sub_tid = (extend.get("sub") if extend else None) or self._get_classes(tid)[0].get('type_id')
        if not sub_tid: return {"list": []}
        try:
            res = spider.categoryContent(sub_tid, pg, filter, extend)
            if res and 'list' in res:
                res['list'] = [self._normalize_vod(v, tid, spider, process_img=False) for v in res['list']]
            return res or {"list": []}
        except: return {"list": []}
    def detailContent(self, array):
        if not array or "|" not in array[0]: return {"list": []}
        py_path, real_id = array[0].split("|", 1)
        spider, _ = self._load_spider_instance(py_path)
        if not spider: return {"list": []}
        try:
            res = spider.detailContent([real_id])
            if res and 'list' in res:
                vod = self._normalize_vod(res['list'][0], py_path, spider, process_img=True)
                if vod.get('vod_play_url'):
                    vod['vod_play_url'] = "#".join([f"{p.split('$')[0]}${py_path}|{p.split('$',1)[1]}" for p in vod['vod_play_url'].split('#')])
                return {"list": [vod]}
        except: pass
        return {"list": []}
    def playerContent(self, flag, id, vipFlags):
        if "|" not in id: return {"parse": 0, "url": "error"}
        py_path, real_id = id.split("|", 1)
        spider, _ = self._load_spider_instance(py_path)
        if spider:
            try: return spider.playerContent(flag, real_id, vipFlags)
            except: pass
        return {"parse": 0, "url": ""}
    def searchContent(self, key, quick, pg="1"):
        if not key or not os.path.exists(self.scan_path): return {"list": []}
        files = [f for f in os.listdir(self.scan_path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
        res_list = []        
        def fetch_search(py_path):
            try:
                spider, _ = self._load_spider_instance(py_path)
                if spider and hasattr(spider, 'searchContent'):
                    res = spider.searchContent(key, quick, pg)
                    if res and 'list' in res:
                        return [self._normalize_vod(v, py_path, spider, process_img=False) for v in res['list']]
            except: pass
            return []
        with ThreadPoolExecutor(max_workers=5) as executor:
            # 提交所有任务
            future_to_path = {executor.submit(fetch_search, os.path.join(self.scan_path, f)): f for f in files}
            # 获取结果
            for future in as_completed(future_to_path):
                res_list.extend(future.result())                
        return {"list": res_list[:100]}
    def localProxy(self, param): return None
