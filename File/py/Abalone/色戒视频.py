# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 色戒视频
# 播放页即详情页；只动态获取最新网址；分类/筛选本地写死；init/homeContent 零网络

import re, json, time, html
from urllib.parse import urljoin, quote, unquote
import requests, urllib3
urllib3.disable_warnings()

try:
    from base.spider import Spider as BaseSpider
except Exception:
    class BaseSpider(object):
        def fetch(self, *args, **kwargs): return None

class Spider(BaseSpider):
    def __init__(self):
        self.base = 'https://www.sdjie.xyz'
        self.host = self.base
        self.host_time = 0
        self.headers = {
            'User-Agent':'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
            'Accept':'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language':'zh-CN,zh;q=0.9'
        }
        self.classes = [
            {'type_id':'6','type_name':'网红主播'}, {'type_id':'23','type_name':'绝色佳人'},
            {'type_id':'8','type_name':'探花精选'}, {'type_id':'15','type_name':'家庭乱伦'},
            {'type_id':'9','type_name':'国产自拍'}, {'type_id':'10','type_name':'网曝门'},
            {'type_id':'32','type_name':'韩国主播'}, {'type_id':'13','type_name':'SM调教'},
            {'type_id':'33','type_name':'泰国风情'}, {'type_id':'7','type_name':'国产传媒'},
            {'type_id':'28','type_name':'电车痴汉'}, {'type_id':'30','type_name':'日本动漫'}
        ]
        self.filters = {c['type_id']:[{'key':'sort','name':'排序','value':[{'n':'最新','v':''}]}] for c in self.classes}

    def log(self, msg):
        try: print(msg)
        except Exception: pass

    def getName(self): return '色戒视频'
    def getDependence(self): return []
    def init(self, extend=''):
        return None
    def destroy(self): return None

    def homeContent(self, filter):
        return {'class': self.classes, 'filters': self.filters if filter else {}}
    def getHomeContent(self, filter):
        return self.homeContent(filter)

    def siteInfo(self):
        return {'current':self.host, 'hint':self.base, 'route':'maccms-play-first', 'home':'zero-network'}

    def req(self, url, timeout=15):
        return requests.get(url, headers=self.headers, timeout=timeout, verify=False, allow_redirects=True)

    def textOf(self, r):
        if r is None: return ''
        if hasattr(r, 'encoding') and not r.encoding:
            r.encoding = 'utf-8'
        try: return r.text
        except Exception:
            try: return r.content.decode('utf-8','ignore')
            except Exception: return str(r)

    def getHost(self, force=False):
        if not force and self.host and time.time() - self.host_time < 1800:
            return self.host
        candidates = [self.base]
        try:
            r = self.req(self.base + '/', timeout=10)
            txt = self.textOf(r)
            final = (getattr(r, 'url', '') or self.base).rstrip('/')
            for x in re.findall(r'https?://(?:www\.)?[a-z0-9.-]*(?:sdjie|sejie)[a-z0-9.-]*\.[a-z]{2,}', txt + ' ' + final, re.I):
                candidates.append(x.rstrip('/'))
        except Exception as e:
            self.log('色戒视频入口探测失败 %s %s' % (self.base, e))
        seen = []
        for c in candidates:
            c = c.rstrip('/')
            if c and c not in seen: seen.append(c)
        for h in seen:
            try:
                r = self.req(h + '/', timeout=10)
                txt = self.textOf(r)
                if r.status_code < 400 and ('色戒视频' in txt or '/index.php/vod/play/' in txt or '/index.php/vod/type/' in txt):
                    self.host = h
                    self.host_time = time.time()
                    return self.host
            except Exception as e:
                self.log('色戒视频候选域名不可用 %s %s' % (h, e))
        self.host = self.base
        self.host_time = time.time()
        return self.host

    def absUrl(self, u):
        if not u: return ''
        u = html.unescape(u).strip().replace('\\/', '/')
        if u.startswith('//'): return 'https:' + u
        return urljoin(self.getHost().rstrip('/') + '/', u)

    def clean(self, s):
        s = html.unescape(str(s or ''))
        s = re.sub(r'<script[\s\S]*?</script>|<style[\s\S]*?</style>', '', s, flags=re.I)
        s = re.sub(r'<[^>]+>', ' ', s)
        s = re.sub(r'\s+', ' ', s).strip()
        return s

    def parseList(self, txt):
        vods, seen = [], set()
        blocks = re.findall(r'(<div[^>]+class=["\'][^"\']*col-6[^"\']*["\'][\s\S]*?)(?=<div[^>]+class=["\'][^"\']*col-6|</section>|<div class="pagination"|$)', txt, re.I)
        if not blocks:
            blocks = re.findall(r'(<div[^>]+class=["\'][^"\']*\bitem\b[^"\']*["\'][\s\S]*?)(?=<div[^>]+class=["\'][^"\']*\bitem\b|</section>|<div class="pagination"|$)', txt, re.I)
        if not blocks:
            blocks = re.findall(r'(<a[^>]+href=["\'][^"\']*/index\.php/vod/play/id/\d+/sid/\d+/nid/\d+\.html["\'][\s\S]{0,1200}?</a>)', txt, re.I)
        for b in blocks:
            try:
                m = re.search(r'href=["\']([^"\']*/index\.php/vod/play/id/(\d+)/sid/\d+/nid/\d+\.html)["\']', b, re.I)
                if not m: continue
                url = self.absUrl(m.group(1))
                if url in seen: continue
                seen.add(url)
                pic = ''
                pm = re.search(r'(?:data-src|data-original|data-lazy-src)=["\']([^"\']+)["\']', b, re.I)
                if not pm:
                    pm = re.search(r'\ssrc=["\']([^"\']+)["\']', b, re.I)
                if pm:
                    pic = self.absUrl(pm.group(1))
                    if 'placeholder' in pic or pic.endswith('/static/assets/images/placeholder-md.jpg'):
                        pic = ''
                name = ''
                for pat in [r'<h6[^>]+class=["\'][^"\']*title[^"\']*["\'][^>]*>\s*<a[^>]*>([\s\S]{0,260}?)</a>', r'title=["\']([^"\']+)["\']', r'alt=["\']([^"\']+)["\']']:
                    nm = re.search(pat, b, re.I)
                    if nm:
                        name = self.clean(nm.group(1)); break
                if not name: name = '色戒视频-%s' % m.group(2)
                remark = ''
                vods.append({'vod_id':url,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            except Exception as e:
                self.log('色戒视频单条解析失败 %s' % e)
        return vods

    def homeVideoContent(self):
        try:
            txt = self.textOf(self.req(self.getHost() + '/', timeout=18))
            return {'list': self.parseList(txt)[:24]}
        except Exception as e:
            self.log('色戒视频首页视频失败 %s' % e)
            return {'list': []}

    def parseAjaxList(self, data, tid):
        vods, seen = [], set()
        lst = data.get('list') or []
        # 严格校验：只接受接口中 type_id 等于当前分类 tid 的条目，禁止全站最新/搜索结果冒充分类
        real = []
        for it in lst:
            if str(it.get('type_id') or '') == str(tid):
                real.append(it)
        if not real:
            return []
        host = self.getHost()
        for it in real:
            try:
                vid = str(it.get('vod_id') or '').strip()
                if not vid or vid in seen: continue
                seen.add(vid)
                url = '%s/index.php/vod/play/id/%s/sid/1/nid/1.html' % (host, vid)
                pic = str(it.get('vod_pic') or '').replace('\\/', '/')
                vods.append({
                    'vod_id': url,
                    'vod_name': self.clean(it.get('vod_name') or ('色戒视频-%s' % vid)),
                    'vod_pic': self.absUrl(pic) if pic else '',
                    'vod_remarks': self.clean(it.get('vod_class') or it.get('vod_remarks') or '')
                })
            except Exception as e:
                self.log('色戒视频ajax单条失败 %s' % e)
        return vods

    def categoryContent(self, tid, pg, filter, extend):
        try: pg = int(pg or 1)
        except Exception: pg = 1
        try:
            host = self.getHost()
            # 只取分类自身页面；不再用搜索/聚合/最新兜底
            url = '%s/index.php/vod/type/id/%s.html' % (host, tid) if pg <= 1 else '%s/index.php/vod/type/id/%s/page/%s.html' % (host, tid, pg)
            txt = self.textOf(self.req(url, timeout=18))
            vods = self.parseList(txt)
            pagecount, total = pg, len(vods)
            if not vods:
                api = '%s/index.php/ajax/data?mid=1&tid=%s&page=%s&limit=20' % (host, tid, pg)
                r = self.req(api, timeout=18)
                try:
                    data = r.json()
                except Exception:
                    data = json.loads(self.textOf(r) or '{}')
                vods = self.parseAjaxList(data, tid)
                pagecount = int(data.get('pagecount') or pg) if vods else pg
                total = int(data.get('total') or len(vods)) if vods else 0
            return {'list':vods, 'page':pg, 'pagecount':pagecount, 'limit':20, 'total':total}
        except Exception as e:
            self.log('色戒视频分类失败 tid=%s pg=%s %s' % (tid, pg, e))
            return {'list':[], 'page':pg, 'pagecount':pg, 'limit':20, 'total':0}

    def searchContent(self, key, quick, pg='1'):
        try:
            host = self.getHost()
            url = '%s/index.php/vod/search.html?wd=%s' % (host, quote(key or ''))
            txt = self.textOf(self.req(url, timeout=18))
            return {'list': self.parseList(txt)}
        except Exception as e:
            self.log('色戒视频搜索失败 %s %s' % (key, e))
            return {'list': []}
    def searchContentPage(self, key, quick, pg):
        return self.searchContent(key, quick, pg)

    def parsePlayerJson(self, txt):
        m = re.search(r'player_\w+\s*=\s*(\{[\s\S]*?\})\s*</script>', txt, re.I)
        if not m:
            m = re.search(r'player_\w+\s*=\s*(\{[\s\S]{0,4000}?\})', txt, re.I)
        if not m: return {}
        raw = m.group(1)
        try:
            return json.loads(raw)
        except Exception:
            try: return json.loads(raw.encode('utf-8').decode('unicode_escape'))
            except Exception: return {}

    def detailContent(self, ids):
        try:
            url = ids[0] if isinstance(ids, list) else ids
            txt = self.textOf(self.req(url, timeout=18))
            data = self.parsePlayerJson(txt)
            vod_data = data.get('vod_data') or {}
            name = vod_data.get('vod_name') or ''
            typ = vod_data.get('vod_class') or ''
            if not name:
                m = re.search(r'<title[^>]*>([\s\S]*?)(?:-|_)?\s*高清视频在线观看[\s\S]*?</title>', txt, re.I)
                if m: name = self.clean(m.group(1))
            if not name:
                m = re.search(r'<title[^>]*>([\s\S]*?)</title>', txt, re.I)
                if m: name = self.clean(m.group(1)).replace('- 色戒视频','')
            pic = ''
            pm = re.search(r'(?:data-src|data-original|data-lazy-src)=["\']([^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\']', txt, re.I)
            if not pm:
                pm = re.search(r'\ssrc=["\']([^"\']+\.(?:jpg|jpeg|png|webp)[^"\']*)["\']', txt, re.I)
            if pm:
                pic = self.absUrl(pm.group(1))
                if 'placeholder' in pic:
                    pic = ''
            desc = name
            vod = {'vod_id':url, 'vod_name':name or '色戒视频', 'vod_pic':pic, 'type_name':self.clean(typ), 'vod_content':desc, 'vod_play_from':'色戒视频', 'vod_play_url':'播放$%s' % url}
            return {'list':[vod]}
        except Exception as e:
            self.log('色戒视频详情失败 %s' % e)
            return {'list': []}

    def playerContent(self, flag, id, vipFlags):
        try:
            if self.isMedia(id):
                return {'parse':0, 'url':id, 'header':self.headers}
            txt = self.textOf(self.req(id, timeout=18))
            data = self.parsePlayerJson(txt)
            play = data.get('url') or ''
            enc = data.get('encrypt')
            if enc == 1:
                play = unquote(play)
            elif enc == 2:
                import base64
                play = unquote(base64.b64decode(play).decode('utf-8','ignore'))
            if not play:
                m = re.search(r'(https?://[^"\'<>\s]+\.(?:m3u8|mp4)[^"\'<>\s]*)', txt, re.I)
                if m: play = m.group(1)
            play = self.absUrl(play) if play else id
            return {'parse':0 if self.isMedia(play) else 1, 'url':play, 'header':self.headers}
        except Exception as e:
            self.log('色戒视频播放失败 %s' % e)
            return {'parse':1, 'url':id, 'header':self.headers}

    def isMedia(self, u):
        return bool(re.search(r'\.(m3u8|mp4)(\?|$)', str(u), re.I))

spider = Spider()