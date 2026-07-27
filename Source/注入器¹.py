import sys
sys.dont_write_bytecode = True

import os
import json
import hashlib
import urllib.request
import urllib.parse
from base.spider import Spider

class Spider(Spider):
    # 硬编码扫描路径
    PATH_1 = "/storage/emulated/0/Film-TV/File/py/Abalone"
    PATH_2 = "F:\\模拟共享\\Film-TV\\File\\py\\Abalone"

    # registry.json 路径
    REGISTRY_PATH = "/storage/emulated/0/TV/CustomCsp/registry.json"

    # 自动生成站点的前缀
    GENERATED_PREFIX = "local_py_"

    # 封面图标（Base64 SVG）
    ICON_SCAN = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM0Q0FGNTAiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMSIgY3k9IjExIiByPSI4Ii8+PGxpbmUgeDE9IjIxIiB5MT0iMjEiIHgyPSIxNi42NSIgeTI9IjE2LjY1Ii8+PC9zdmc+"
    ICON_CLEAR = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNFNTM3MzciIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cG9seWxpbmUgcG9pbnRzPSIzIDYgNSA2IDIxIDYiLz48cGF0aCBkPSJNMTkgNnYxNGEyIDIgMCAwIDEtMiAySDdhMiAyIDAgMCAxLTItMlY2bDMgMEg0Ii8+PHBhdGggZD0iTTEwIDExdjYiLz48cGF0aCBkPSJNMTQgMTF2NiIvPjwvc3ZnPg=="

    def init(self, extend=""):
        self.scan_paths = []
        if os.path.exists(self.PATH_1):
            self.scan_paths.append(self.PATH_1)
        if os.path.exists(self.PATH_2) and self.PATH_2 not in self.scan_paths:
            self.scan_paths.append(self.PATH_2)
        if not self.scan_paths:
            self.scan_paths.append(os.path.dirname(os.path.abspath(__file__)))

    def getName(self):
        return "Python爬虫注入器"

    def homeContent(self, filter):
        count = len(self._get_injected_sites_raw())
        classes = [
            {"type_id": "injected", "type_name": f"已注入站点 ({count})"}
        ]
        return {"class": classes, "list": []}

    def categoryContent(self, tid, pg, filter, extend):
        if tid == "injected":
            items = [
                {
                    "vod_id": "__inject__",
                    "vod_name": "注入",
                    "vod_remarks": "扫描目录，注入所有spider",
                    "vod_pic": self.ICON_SCAN,
                    "action": "inject"
                },
                {
                    "vod_id": "__clear__",
                    "vod_name": "清除",
                    "vod_remarks": "移除所有注入站点",
                    "vod_pic": self.ICON_CLEAR,
                    "action": "clear"
                }
            ]
            return self._paged_result(items, pg)
        else:
            return {"list": []}

    def detailContent(self, array):
        return {"list": []}

    def action(self, action):
        if action == "inject":
            return self._action_inject()
        elif action == "clear":
            return self._action_clear()
        else:
            return {"code": 0, "msg": "未知操作"}

    def searchContent(self, key, quick, pg="1"):
        key = key.lower()
        items = []
        for path in self.scan_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if key in f.lower() and f.endswith(".py") and not f.startswith("__"):
                    items.append({
                        "vod_id": hashlib.md5(f.encode()).hexdigest()[:16],
                        "vod_name": f,
                        "vod_remarks": "文件",
                        "vod_pic": ""
                    })
        return self._paged_result(items, pg)

    def playerContent(self, flag, id, vipFlags):
        return {"parse": 0, "url": ""}

    def destroy(self):
        pass

    # ----------------- 辅助方法 -----------------
    def _load_registry(self):
        if os.path.exists(self.REGISTRY_PATH):
            try:
                with open(self.REGISTRY_PATH, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    if "items" in data:
                        return data
            except:
                pass
        return {"enabled": True, "items": []}

    def _save_registry(self, registry):
        with open(self.REGISTRY_PATH, 'w', encoding='utf-8') as f:
            json.dump(registry, f, ensure_ascii=False, indent=2)

    def _item_key(self, item):
        site = item.get("site", {})
        return site.get("key", "")

    def _is_generated(self, item):
        key = self._item_key(item)
        return key.startswith(self.GENERATED_PREFIX)

    def _get_injected_sites_raw(self):
        registry = self._load_registry()
        return [item for item in registry.get("items", []) if self._is_generated(item)]

    def _build_site(self, file_path):
        name = os.path.basename(file_path)
        if name.endswith(".py"):
            display_name = name[:-3] + "·注入"
        else:
            display_name = name + "·注入"
        api = "file://" + file_path
        key = self.GENERATED_PREFIX + hashlib.md5(file_path.encode()).hexdigest()[:12]
        return {
            "key": key,
            "name": display_name,
            "type": 3,
            "api": api,
            "searchable": 1,
            "quickSearch": 1
        }

    def _action_inject(self):
        registry = self._load_registry()
        # 保留非自动生成的手工站点
        manual_items = [item for item in registry.get("items", []) if not self._is_generated(item)]

        # 生成新的注入站点列表
        new_items = []
        for path in self.scan_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if f.endswith(".py") and not f.startswith("__"):
                    full_path = os.path.join(path, f)
                    site = self._build_site(full_path)
                    new_items.append({
                        "id": site["key"],
                        "enabled": True,
                        "kind": "csp",
                        "site": site
                    })

        # 将新注入的站点放在最前面（置顶）
        registry["items"] = new_items + manual_items

        self._save_registry(registry)
        self._reload_app()

        count = len(new_items)
        return {"code": 0, "msg": f"✅ 已注入 {count} 个 Python 站点（已置顶）"}

    def _action_clear(self):
        registry = self._load_registry()
        registry["items"] = [item for item in registry.get("items", []) if not self._is_generated(item)]
        self._save_registry(registry)
        self._reload_app()
        return {"code": 0, "msg": "🗑 已移除所有本地注入站点"}

    def _reload_app(self):
        try:
            for port in range(9978, 9999):
                try:
                    req = urllib.request.Request(f"http://127.0.0.1:{port}/manage/configs", headers={"Accept": "application/json"})
                    with urllib.request.urlopen(req, timeout=0.5) as resp:
                        data = json.loads(resp.read().decode())
                        items = data.get("items", [])
                        for item in items:
                            if item.get("type") == 0 and item.get("active"):
                                url = item.get("url", "")
                                if url:
                                    reload_req = urllib.request.Request(
                                        f"http://127.0.0.1:{port}/manage/config/use?type=0&url={urllib.parse.quote(url)}"
                                    )
                                    urllib.request.urlopen(reload_req, timeout=0.5)
                                    return
                except:
                    continue
        except:
            pass

    def _paged_result(self, items, pg):
        return {"list": items, "page": 1, "pagecount": 1, "total": len(items)}