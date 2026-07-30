import sys
sys.dont_write_bytecode = True

import os
import json
import hashlib
import urllib.request
import urllib.parse
from base.spider import Spider

class Spider(Spider):
    PY_PATH_1 = "/storage/emulated/0/Film-TV/File/py/Hunter"
    PY_PATH_2 = "F:\\模拟共享\\Film-TV\\File\\py\\Hunter"
    HTML_PATH_1 = "/storage/emulated/0/Film-TV/File/html/Hunter"
    HTML_PATH_2 = "F:\\模拟共享\\Film-TV\\File\\html\\Hunter"

    REGISTRY_PATH = "/storage/emulated/0/TV/CustomCsp/registry.json"
    GENERATED_PREFIX = "local_py_"

    ICON_SCAN = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM0Q0FGNTAiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMSIgY3k9IjExIiByPSI4Ii8+PGxpbmUgeDE9IjIxIiB5MT0iMjEiIHgyPSIxNi42NSIgeTI9IjE2LjY1Ii8+PC9zdmc+"
    ICON_CLEAR = "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNFNTM3MzciIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cG9seWxpbmUgcG9pbnRzPSIzIDYgNSA2IDIxIDYiLz48cGF0aCBkPSJNMTkgNnYxNGEyIDIgMCAwIDEtMiAySDdhMiAyIDAgMCAxLTItMlY2bDMgMEg0Ii8+PHBhdGggZD0iTTEwIDExdjYiLz48cGF0aCBkPSJNMTQgMTF2NiIvPjwvc3ZnPg=="

    def init(self, extend=""):
        self.py_paths = []
        if os.path.exists(self.PY_PATH_1):
            self.py_paths.append(self.PY_PATH_1)
        if os.path.exists(self.PY_PATH_2) and self.PY_PATH_2 not in self.py_paths:
            self.py_paths.append(self.PY_PATH_2)

        self.html_paths = []
        if os.path.exists(self.HTML_PATH_1):
            self.html_paths.append(self.HTML_PATH_1)
        if os.path.exists(self.HTML_PATH_2) and self.HTML_PATH_2 not in self.html_paths:
            self.html_paths.append(self.HTML_PATH_2)

    def getName(self):
        return "注入器 (PY+HTML)"

    def _to_superscript(self, num):
        superscript_map = {
            '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴',
            '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹'
        }
        return ''.join(superscript_map[ch] for ch in str(num))

    def homeContent(self, filter):
        auto_items = self._get_injected_sites_raw()
        py_count, html_count = self._count_injected_types(auto_items)
        py_sup = self._to_superscript(py_count)
        html_sup = self._to_superscript(html_count)
        class_name = f"已注入站点:   html{html_sup}   py{py_sup}"
        classes = [{"type_id": "injected", "type_name": class_name}]
        return {"class": classes, "list": []}

    def _count_injected_types(self, items):
        py = 0
        html = 0
        for item in items:
            kind = item.get("kind", "")
            site = item.get("site", {})
            if kind == "webHome" or site.get("webHome") or site.get("homePage"):
                html += 1
            else:
                py += 1
        return py, html

    def categoryContent(self, tid, pg, filter, extend):
        if tid == "injected":
            items = [
                {"vod_id": "__inject__", "vod_name": "注入", "vod_remarks": "扫描目录，注入所有spider", "vod_pic": self.ICON_SCAN, "action": "inject"},
                {"vod_id": "__clear__", "vod_name": "清除", "vod_remarks": "移除所有注入站点", "vod_pic": self.ICON_CLEAR, "action": "clear"}
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
        all_paths = self.py_paths + self.html_paths
        for path in all_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if key in f.lower() and (f.endswith(".py") or f.endswith(".html")) and not f.startswith("__"):
                    items.append({"vod_id": hashlib.md5(f.encode()).hexdigest()[:16], "vod_name": f, "vod_remarks": "文件", "vod_pic": ""})
        return self._paged_result(items, pg)

    def playerContent(self, flag, id, vipFlags):
        return {"parse": 0, "url": ""}

    def destroy(self):
        pass

    # --------------------- 辅助方法 ---------------------
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

    # ===== 修改：显示后缀 =====
    def _build_site(self, file_path, ext):
        name = os.path.basename(file_path)
        if ext == '.py':
            display_name = name[:-3] + "ᵖʸ" if name.endswith(".py") else name + "ᵖʸ"
        else:  # .html
            display_name = name[:-5] + "ʰᵗᵐˡ" if name.endswith(".html") else name + "ʰᵗᵐˡ"
        key = self.GENERATED_PREFIX + hashlib.md5(file_path.encode()).hexdigest()[:12]
        site = {"key": key, "name": display_name, "type": 3, "searchable": 1, "quickSearch": 1}
        if ext == '.py':
            site["api"] = "file://" + file_path
        else:
            site["api"] = ""
            site["homePage"] = "file://" + file_path
            site["webHome"] = True
        return site

    # ===== 修改：先 HTML 后 Py =====
    def _action_inject(self):
        registry = self._load_registry()
        manual_items = [item for item in registry.get("items", []) if not self._is_generated(item)]
        new_items = []

        # HTML 优先
        for path in self.html_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if f.endswith(".html") and not f.startswith("__"):
                    full_path = os.path.join(path, f)
                    site = self._build_site(full_path, '.html')
                    new_items.append({"id": site["key"], "enabled": True, "kind": "webHome", "site": site})

        # Py 其次
        for path in self.py_paths:
            if not os.path.exists(path):
                continue
            for f in os.listdir(path):
                if f.endswith(".py") and not f.startswith("__"):
                    full_path = os.path.join(path, f)
                    site = self._build_site(full_path, '.py')
                    new_items.append({"id": site["key"], "enabled": True, "kind": "csp", "site": site})

        registry["items"] = new_items + manual_items
        self._save_registry(registry)
        self._reload_app()
        count = len(new_items)
        return {"code": 0, "msg": f"✅ 已注入 {count} 个站点（HTML + Py，已置顶）"}

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