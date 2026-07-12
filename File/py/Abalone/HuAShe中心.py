# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - HuAShe中心
# 规则：只动态获取最新网址；class/filters 本地写死；homeContent/getHomeContent 零网络
import re, json, html, urllib.parse
try:
    import requests
except Exception:
    requests = None

class Spider(object):
    def __init__(self):
        self.host = ''
        self.fallback = 'https://hua28.xyz'
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer': self.fallback + '/'
        }
        self.classes = [
            {'type_id':'1','type_name':'传媒'}, {'type_id':'2','type_name':'日本'},
            {'type_id':'5','type_name':'香蕉视频'}, {'type_id':'6','type_name':'91Fans'},
            {'type_id':'9','type_name':'大象传媒'}, {'type_id':'10','type_name':'天美传媒'},
            {'type_id':'11','type_name':'微密圈'}, {'type_id':'12','type_name':'果冻传媒'},
            {'type_id':'13','type_name':'加勒比'}, {'type_id':'14','type_name':'一本道'},
            {'type_id':'15','type_name':'Heyzo'}, {'type_id':'16','type_name':'天然素人'},
            {'type_id':'17','type_name':'麻豆传媒'}, {'type_id':'18','type_name':'糖心vlog'},
            {'type_id':'20','type_name':'国产精品'}, {'type_id':'21','type_name':'人妻paco'},
            {'type_id':'23','type_name':'onlyfans'}, {'type_id':'24','type_name':'国产视频'},
            {'type_id':'25','type_name':'Viral'}
        ]
        self.filters = {'1':[{'key':'sort','name':'排序','value':[{'n':'最新','v':''}]}]}

    def init(self, extend=''):
        return None

    def siteName(self):
        return 'HuAShe中心'

    def siteInfo(self):
        return {'name':'HuAShe中心','type':'adult','fallback':self.fallback,'rule':'dynamic-host-only'}

    def homeContent(self, filter):
        return {'class': self.classes, 'filters': self.filters}

    def getHomeContent(self, filter=False):
        return self.homeContent(filter)

    def homeVideoContent(self):
        try:
            host = self.getHost()
            txt = self.textOf(self.req(host + '/', timeout=15))
            return {'list': self.parseList(txt, host)[:30]}
        except Exception:
            return {'list': []}

    def categoryContent(self, tid, pg, filter, extend):
        host = self.getHost()
        pg = int(pg or 1)
        # 该站 /vodtype/{tid}/2/ 实测重复第一页；pg>1 直接空页，避免壳子翻页重复
        if pg > 1:
            return {'list': [], 'page': pg, 'pagecount': pg, 'limit': 0, 'total': 0}
        url = '%s/vodtype/%s/' % (host, tid)
        try:
            txt = self.textOf(self.req(url, timeout=18))
            vods = self.parseList(txt, host)
            return {'list': vods, 'page': pg, 'pagecount': pg, 'limit': len(vods), 'total': len(vods)}
        except Exception:
            return {'list': [], 'page': pg, 'pagecount': pg, 'limit': 0, 'total': 0}

    def searchContent(self, key, quick=False, pg='1'):
        return self.searchContentPage(key, quick, pg)

    def searchContentPage(self, key, quick=False, pg='1'):
        host = self.getHost()
        wd = urllib.parse.quote(str(key or '').strip())
        pg = int(pg or 1)
        # 站点可用搜索：/vodsearch/-------------.html?wd=xxx；分页未稳定，pg>1 直接尝试后为空不重复
        url = '%s/vodsearch/-------------.html?wd=%s' % (host, wd) if pg <= 1 else '%s/vodsearch/-------------%s---.html?wd=%s' % (host, pg, wd)
        try:
            txt = self.textOf(self.req(url, timeout=18))
            vods = self.parseList(txt, host)
            return {'list': vods, 'page': pg, 'pagecount': 999999 if vods and pg <= 1 else pg, 'limit': len(vods), 'total': 999999 if vods else 0}
        except Exception:
            return {'list': [], 'page': pg, 'pagecount': pg, 'limit': 0, 'total': 0}

    def detailContent(self, ids):
        host = self.getHost()
        vid = ids[0] if isinstance(ids, list) else ids
        url = self.absUrl(str(vid), host)
        try:
            txt = self.textOf(self.req(url, timeout=18))
            name = self.clean(self.pick(txt, r'<title>(.*?)</title>'))
            name = re.sub(r'(在线观看|在线高清观看).*$', '', name).replace(' - HuAShe中心','').strip(' -')
            if not name:
                name = self.clean(self.pick(txt, r'name"\s*:\s*"([^"]+)"'))
            pic = self.pick(txt, r'(?:poster|background)\s*:\s*url\([\'\"]?([^\'\")]+)') or self.pick(txt, r'<img[^>]+(?:data-src|data-original|src)=["\']([^"\']+)["\']')
            pic = self.absUrl(html.unescape(pic), host) if pic else ''
            cls = self.clean(self.pick(txt, r'<a[^>]+href=["\']/vodtype/\d+/?["\'][^>]*>(.*?)</a>'))
            m3u8 = self.getPlayUrl(txt)
            if not name:
                name = url.rstrip('/').split('/')[-1]
            vod = {
                'vod_id': url, 'vod_name': name, 'vod_pic': pic, 'type_name': cls,
                'vod_year': '', 'vod_area': '', 'vod_remarks': '在线播放',
                'vod_content': name,
                'vod_play_from': 'dplayer',
                'vod_play_url': '播放$%s' % (m3u8 or url)
            }
            return {'list': [vod]}
        except Exception:
            return {'list': [{'vod_id': url, 'vod_name': '播放', 'vod_play_from': 'dplayer', 'vod_play_url': '播放$%s' % url}]}

    def playerContent(self, flag, id, vipFlags):
        host = self.getHost()
        url = self.absUrl(str(id), host)
        try:
            if self.isVideo(url):
                return {'parse': 0, 'url': url, 'header': self.playHeader(url)}
            txt = self.textOf(self.req(url, timeout=18))
            real = self.getPlayUrl(txt)
            if real:
                return {'parse': 0, 'url': real, 'header': self.playHeader(real)}
        except Exception:
            pass
        return {'parse': 1, 'url': url}

    def getHost(self):
        if self.host:
            return self.host
        # 导航/发布页可能更新，这里只动态 host，不动态分类
        cands = [self.fallback, 'https://hua28.xyz', 'https://hua26.xyz']
        for u in cands:
            try:
                r = self.req(u + '/', timeout=8)
                if getattr(r, 'status_code', 0) == 200 and 'vodplay' in self.textOf(r)[:80000]:
                    self.host = u.rstrip('/')
                    return self.host
            except Exception:
                continue
        self.host = self.fallback
        return self.host

    def parseList(self, txt, host):
        vods, seen = [], set()
        # 主选择器：item play 块；兜底：任意 /vodplay 链接附近图片/标题
        blocks = re.findall(r'<div[^>]+class=["\'][^"\']*item[^"\']*play[^"\']*["\'][^>]*>[\s\S]*?(?=<div[^>]+class=["\'][^"\']*item[^"\']*play|</div>\s*</div>\s*</div>|<div[^>]+class=["\'][^"\']*page|</body>)', txt, re.I)
        if not blocks:
            blocks = re.findall(r'<a[^>]+href=["\'][^"\']*/vodplay/\d+-\d+-\d+/?["\'][^>]*>[\s\S]{0,900}</a>', txt, re.I)
        for b in blocks:
            try:
                href = self.pick(b, r'href=["\']([^"\']*/vodplay/\d+-\d+-\d+/?)["\']')
                if not href: continue
                vid = self.absUrl(href, host)
                if vid in seen: continue
                seen.add(vid)
                name = self.pick(b, r'href=["\'][^"\']*/vodplay/\d+-\d+-\d+/?["\'][^>]*title=["\']([^"\']+)["\']') or self.pick(b, r'alt=["\']《?([^"\']+?)》?[^"\']*["\']')
                if not name:
                    name = self.clean(re.sub(r'<[^>]+>', ' ', b))[:80]
                pic = self.pick(b, r'(?:data-src|data-original|data-lazy-src)=["\']([^"\']+)["\']') or self.pick(b, r'<img[^>]+src=["\']([^"\']+)["\']')
                cls = self.clean(self.pick(b, r'<div[^>]+class=["\'][^"\']*subtitle[^"\']*["\'][^>]*>[\s\S]*?<a[^>]*>(.*?)</a>'))
                vods.append({'vod_id': vid, 'vod_name': self.clean(html.unescape(name)), 'vod_pic': self.absUrl(html.unescape(pic), host) if pic else '', 'vod_remarks': cls})
            except Exception:
                continue
        return vods

    def getPlayUrl(self, txt):
        m = re.search(r'player_aaaa\s*=\s*(\{[\s\S]*?\})\s*</script>', txt, re.I) or re.search(r'player_aaaa\s*=\s*(\{.*?\})', txt, re.I)
        if m:
            try:
                data = json.loads(m.group(1))
                u = data.get('url') or ''
                if data.get('encrypt') == 1:
                    u = urllib.parse.unquote(u)
                if u:
                    return u.replace('\\/', '/')
            except Exception:
                raw = m.group(1).replace('\\/', '/')
                u = self.pick(raw, r'"url"\s*:\s*"([^"]+)"')
                if u: return u
        u = self.pick(txt, r'(https?://[^"\']+?\.m3u8[^"\']*)')
        return u.replace('\\/', '/') if u else ''

    def req(self, url, timeout=15):
        if hasattr(self, 'fetch'):
            try:
                return self.fetch(url, headers=self.headers, timeout=timeout)
            except Exception:
                pass
        if requests is None:
            raise Exception('requests unavailable')
        return requests.get(url, headers=self.headers, timeout=timeout, verify=False)

    def textOf(self, r):
        if isinstance(r, str): return r
        if isinstance(r, dict): return r.get('content') or r.get('text') or ''
        if hasattr(r, 'content'):
            try: return r.content.decode('utf-8', 'ignore')
            except Exception: return r.text
        return str(r or '')

    def absUrl(self, u, host):
        u = str(u or '').strip()
        if not u: return ''
        if u.startswith('//'): return 'https:' + u
        if u.startswith('http'): return u
        if not u.startswith('/'): u = '/' + u
        return host.rstrip('/') + u

    def pick(self, s, pat):
        m = re.search(pat, s or '', re.I|re.S)
        return m.group(1).strip() if m else ''

    def clean(self, s):
        s = html.unescape(str(s or ''))
        s = re.sub(r'<script[\s\S]*?</script>', ' ', s, flags=re.I)
        s = re.sub(r'<style[\s\S]*?</style>', ' ', s, flags=re.I)
        s = re.sub(r'<[^>]+>', ' ', s)
        return re.sub(r'\s+', ' ', s).strip()

    def isVideo(self, u):
        return bool(re.search(r'\.(m3u8|mp4)(\?|$)', str(u), re.I))

    def playHeader(self, u):
        return {'User-Agent': self.headers['User-Agent'], 'Referer': self.getHost() + '/'}

spider = Spider()