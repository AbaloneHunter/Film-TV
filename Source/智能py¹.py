# -*- coding: utf-8 -*-
import os
import sys
import re
import hashlib
import inspect
import importlib.util
import json
import base64
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad
from base.spider import Spider

class Spider(Spider):
    SCAN_PATH = "/storage/emulated/0/Film-TV/File/py/Abalone"
    CACHE_DIR_NAME = ".spider_cache"
    MAX_CACHE_SIZE = 30

    def init(self, extend):
        self.extend_config = extend or {}
        if extend and isinstance(extend, dict) and extend.get('path'):
            self.scan_path = extend['path']
        else:
            self.scan_path = self.SCAN_PATH
            
        self.class_cache = {}
        self.spider_cache = {}
        self.cache_mtime = {}
        self.cache_dir = os.path.join(self.scan_path, self.CACHE_DIR_NAME)
        
        if not os.path.exists(self.cache_dir):
            try: os.makedirs(self.cache_dir)
            except: self.cache_dir = None
            
        try:
            self.SELF_NAME = os.path.basename(inspect.getfile(inspect.currentframe()))
        except: self.SELF_NAME = "智能py.py"

    def getName(self): return "智能聚合(精简版)"

    # --- 核心工具函数：通用数据清洗 ---
    
    def _normalize_vod(self, v, py_path, spider=None):
        """统一处理视频数据：ID前缀、字段映射、图片修复、代理解密"""
        # 1. ID 处理
        vid = v.get('vod_id') or v.get('id') or ""
        if vid:
            v['vod_id'] = f"{py_path}|{vid}"
        
        # 2. 名称映射
        if not v.get('vod_name'):
            v['vod_name'] = v.get('title') or v.get('name') or "未命名"
            
        # 3. 图片映射与修复
        pic = v.get('vod_pic')
        if not pic:
            # 尝试常见字段
            for key in ['pic', 'img', 'cover', 'poster', 'vImg', 'vThumbUrl', 'picture', 'thumb']:
                if v.get(key): 
                    pic = v[key]
                    break
        
        if pic:
            # 检测是否为需要解密的代理图片 (如51吸瓜)
            if 'getProxyUrl' in pic or ('type=img' in pic and 'url=' in pic):
                pic = self._process_proxy_image(pic, spider, py_path)
            if not pic: # 如果解密失败或原本无效
                pic = self._get_placeholder(vid)
        else:
            pic = self._get_placeholder(vid)
        v['vod_pic'] = pic
            
        # 4. 备注映射
        if not v.get('vod_remarks'):
            v['vod_remarks'] = v.get('remark') or v.get('note') or v.get('tag') or ""
            
        return v

    def _get_placeholder(self, seed):
        return f"https://picsum.photos/seed/{hashlib.md5(str(seed).encode()).hexdigest()[:8]}/300/450.jpg"

    def _process_proxy_image(self, pic_url, spider, py_path):
        """处理代理图片(AES解密)"""
        if not spider or not hasattr(spider, 'd64'): return None
        try:
            from urllib.parse import urlparse, parse_qs
            parsed = urlparse(pic_url)
            params = parse_qs(parsed.query)
            if 'url' in params:
                encrypted = params['url'][0]
                try:
                    script = spider.d64(encrypted)
                    match = re.search(r"data-xkrkllgl=[\'\"]([^\'\"]+)[\'\"]", script)
                    if match and hasattr(spider, 'aesimg'):
                        b64_img = spider.aesimg(base64.b64decode(match.group(1)))
                        return f"data:image/jpeg;base64,{base64.b64encode(b64_img).decode()}"
                except: pass
        except: pass
        return None

    # --- 核心工具函数：加载与缓存 ---

    def _load_spider_instance(self, py_path):
        if not os.path.exists(py_path): return None, "文件不存在"
        if py_path in self.spider_cache and self.cache_mtime.get(py_path) == os.path.getmtime(py_path):
            return self.spider_cache[py_path], "OK"
        
        filename = os.path.basename(py_path)
        if filename == self.SELF_NAME or filename.startswith("__"): return None, "跳过"

        module_name = f"mod_{hashlib.md5(py_path.encode()).hexdigest()}"
        if module_name in sys.modules: del sys.modules[module_name]

        try:
            spec = importlib.util.spec_from_file_location(module_name, py_path)
            module = importlib.util.module_from_spec(spec)
            sys.modules[module_name] = module
            spec.loader.exec_module(module)

            candidates = [getattr(module, name) for name in dir(module) 
                          if isinstance(getattr(module, name), type) 
                          and getattr(getattr(module, name), '__module__') == module_name 
                          and hasattr(getattr(module, name), 'homeContent')]
            
            if not candidates: return None, "无爬虫类"
            target = candidates[0] if len(candidates)==1 else [c for c in candidates if c.__name__!='Spider'][0]
            
            instance = target()
            # 宽松初始化
            if hasattr(instance, 'init'):
                try: instance.init(self.extend_config) if len(inspect.signature(instance.init).parameters) else instance.init()
                except: pass
            
            # 补丁
            if hasattr(instance, 'typeid') and not isinstance(instance.typeid, dict): instance.typeid = {}
            
            self.spider_cache[py_path] = instance
            self.cache_mtime[py_path] = os.path.getmtime(py_path)
            return instance, "OK"
        except Exception as e: return None, str(e)

    def _get_classes(self, py_path, allow_dynamic=False):
        # 优先磁盘缓存 -> 正则提取 -> 动态运行
        current_mtime = os.path.getmtime(py_path)
        
        # 1. 内存缓存
        if py_path in self.class_cache and self.class_cache[py_path][0] == current_mtime:
            return self.class_cache[py_path][1]

        # 2. 磁盘缓存
        cache_file = os.path.join(self.cache_dir, f"{hashlib.md5(py_path.encode()).hexdigest()}.json")
        cached_data = None
        if os.path.exists(cache_file):
            try:
                with open(cache_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                if data.get('mtime') == current_mtime:
                    cached_data = data.get('classes')
                else: os.remove(cache_file)
            except: pass
        
        if cached_data:
            self.class_cache[py_path] = (current_mtime, cached_data)
            return cached_data

        # 3. 动态获取 (如果需要)
        if allow_dynamic:
            spider, _ = self._load_spider_instance(py_path)
            if spider:
                try:
                    res = spider.homeContent({})
                    if res and 'class' in res:
                        classes = res['class']
                        self.class_cache[py_path] = (current_mtime, classes)
                        # 写入磁盘
                        try:
                            with open(cache_file, 'w', encoding='utf-8') as f:
                                json.dump({'mtime': current_mtime, 'classes': classes}, f)
                        except: pass
                        return classes
                except: pass
        
        # 4. 兜底
        return [{'type_id': 'auto', 'type_name': '默认'}]

    # --- 标准接口 ---

    def homeContent(self, filter):
        if not os.path.exists(self.scan_path): return {"class": []}
        files = [f for f in os.listdir(self.scan_path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
        classes = [{"type_id": os.path.join(self.scan_path, f), "type_name": f.replace(".py","")} for f in files]
        filters = {c["type_id"]: [{"key": "sub", "name": "分类", "value": [{"n": i['type_name'], "v": i['type_id']} for i in self._get_classes(c["type_id"], allow_dynamic=True)]}] for c in classes}
        return {"class": classes, "filters": filters}

    def categoryContent(self, tid, pg, filter, extend):
        py_path = tid
        spider, _ = self._load_spider_instance(py_path)
        if not spider: return {"list": []}
        
        # 自动获取分类ID
        sub_tid = extend.get("sub") if extend else None
        if not sub_tid or sub_tid == 'auto':
            cls = self._get_classes(py_path, allow_dynamic=True)
            if cls: sub_tid = cls[0].get('type_id')
        
        if not sub_tid: return {"list": []}

        try:
            res = spider.categoryContent(sub_tid, pg, filter, extend)
            if res and 'list' in res:
                res['list'] = [self._normalize_vod(v, py_path, spider) for v in res['list']]
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
                vod = res['list'][0]
                vod = self._normalize_vod(vod, py_path, spider)
                
                # 处理播放链接
                if vod.get('vod_play_url'):
                    parts = vod['vod_play_url'].split('#')
                    new_parts = []
                    for p in parts:
                        p = p.strip()
                        if not p: continue
                        n, u = (p.split('$$', 1) if '$$' in p else (p.split('$', 1) if '$' in p else ("播放", p)))
                        new_parts.append(f"{n}${py_path}|{u}")
                    vod['vod_play_url'] = "#".join(new_parts)
                res['list'] = [vod]
            return res or {"list": []}
        except: return {"list": []}

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
        all_res = []
        files = [f for f in os.listdir(self.scan_path) if f.endswith(".py") and not f.startswith("__") and f != self.SELF_NAME]
        
        for f in files:
            py_path = os.path.join(self.scan_path, f)
            spider, _ = self._load_spider_instance(py_path)
            if spider and hasattr(spider, 'searchContent'):
                try:
                    res = spider.searchContent(key, quick, pg)
                    if res and 'list' in res:
                        all_res.extend([self._normalize_vod(v, py_path, spider) for v in res['list']])
                except: continue
        return {"list": all_res[:100]}

    def localProxy(self, param): return None
