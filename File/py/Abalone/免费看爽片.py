# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 免费看爽片 6180005.xyz
import re, base64
from urllib.parse import urljoin, quote, unquote, urlparse, parse_qs
try:
    from base.spider import Spider as BaseSpider
except Exception:
    class BaseSpider(object):
        def fetch(self, url, headers=None, timeout=10):
            import requests
            return requests.get(url, headers=headers, timeout=timeout, verify=False)
        def log(self, msg):
            print(msg)

class Spider(BaseSpider):
    def getName(self):
        return '免费看爽片'

    def getDependence(self):
        return []

    def init(self, extend=''):
        self.host = 'https://6180005.xyz'
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        }
        # 页面分类名同样被 XOR(128) 混淆，已按真实页面解出。
        self.classes = [
            {'type_id':'66','type_name':'一区'}, {'type_id':'1','type_name':'二区'},
            {'type_id':'72','type_name':'国产'}, {'type_id':'36','type_name':'主播'},
            {'type_id':'63','type_name':'日欧'}, {'type_id':'64','type_name':'动漫'},
            {'type_id':'65','type_name':'无码'}, {'type_id':'69','type_name':'字幕'},
            {'type_id':'70','type_name':'P站'}, {'type_id':'68','type_name':'厂牌'},
            {'type_id':'71','type_name':'网黄'}, {'type_id':'67','type_name':'JK'},
            {'type_id':'73','type_name':'热门'}, {'type_id':'9','type_name':'FC2'},
            {'type_id':'11','type_name':'S-CUT'}, {'type_id':'12','type_name':'PRES'},
            {'type_id':'26','type_name':'GANA'}, {'type_id':'45','type_name':'carib'},
            {'type_id':'60','type_name':'人妻斩'}, {'type_id':'27','type_name':'LUXU'},
            {'type_id':'28','type_name':'SIRO'}, {'type_id':'29','type_name':'HeyZo'},
            {'type_id':'62','type_name':'10mus'}, {'type_id':'10','type_name':'一本道'}
        ]

    def homeContent(self, filter):
        return {'class': self.classes, 'filters': {}}

    def homeVideoContent(self):
        return {'list': self.getList(self.host + '/index.php/vod/type/id/66.html')}

    def categoryContent(self, tid, pg, filter, extend):
        page = str(pg or '1')
        if page == '1':
            url = self.host + '/index.php/vod/type/id/%s.html' % tid
        else:
            url = self.host + '/index.php/vod/type/id/%s/page/%s.html' % (tid, page)
        vods = self.getList(url)
        return {'list': vods, 'page': int(page), 'pagecount': 999999 if vods else int(page), 'limit': 16, 'total': 999999 if vods else 0}

    def detailContent(self, ids):
        vid = ids[0]
        title, pic, play = '', '', ''
        if '|$|' in vid:
            parts = vid.split('|$|')
            url = parts[0]
            title = unquote(parts[1]) if len(parts) > 1 else ''
            pic = parts[2] if len(parts) > 2 else ''
            pic = self.picProxy(pic)
            play = parts[3] if len(parts) > 3 else ''
        else:
            url = vid
            play = self.getPlayUrl(url)
        title = self.decodeText(title)
        if not title:
            title = self.decodeText(self.cleanName(url.split('/')[-1].split('.html')[0])) or '视频'
        if not play:
            try:
                txt = self.getHtml(url)
                m = re.search(r'[?&]v=(https?://[^"\'<>\s]+?\.m3u8[^"\'<>\s]*)', txt, re.I)
                if m: play = unquote(m.group(1))
                pm = re.search(r'<img[^>]+(?:data-original|src)=["\']([^"\']+)', txt, re.I)
                if pm: pic = self.picProxy(self.fix(pm.group(1)))
            except Exception as e:
                self.log('详情页读取失败 %s' % e)
        vod = {'vod_id': vid, 'vod_name': title, 'vod_pic': pic, 'type_name':'', 'vod_year':'', 'vod_area':'', 'vod_remarks':'', 'vod_actor':'', 'vod_director':'', 'vod_content': title, 'vod_play_from':'直连', 'vod_play_url':'播放$%s' % (play or url)}
        return {'list':[vod]}

    def searchContent(self, key, quick, pg='1'):
        # 该站导航入口的搜索页实测不返回 vodbox 结果；按技能规则避免混入分类/推荐。
        url = self.host + '/index.php/vod/search.html?wd=' + quote(key or '')
        vods = self.getList(url)
        return {'list': vods, 'page': int(pg or '1'), 'total': len(vods)}

    def playerContent(self, flag, id, vipFlags):
        url = self.getPlayUrl(id) if '/html/' in id else id
        url = unquote(url or id)
        if self.isVideoFormat(url):
            if 'token=' not in url and 'd2wexzpo1hxhi0.cloudfront.net/api/app/vid/h5/m3u8' in url:
                token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0aW1lc3RhbXAiOjE3Njg3OTY1NzQ2NDA4MTIwMDAsInR5cGUiOjIsInVpZCI6MzExODUwODJ9.HRSqJRGSN6jXdR6SuX1IJHpvRFIMmdOfhrYIoqiieNQ'
                url += ('&' if '?' in url else '?') + 'token=' + token + '&c=https://zzzhf.pjrwfe.cn'
            return {'parse':0, 'url':url, 'header':self.makePlayHeader(url)}
        return {'parse':1, 'url':id, 'header':self.headers}

    def getList(self, url):
        try:
            txt = self.getHtml(url)
            if self.isNoResultPage(txt):
                return []
            vods, seen = [], set()
            for m in re.finditer(r'<a[^>]+class=["\'][^"\']*vodbox[^"\']*["\'][^>]+href=["\']([^"\']+)["\'][\s\S]*?</a>', txt, re.I):
                block = m.group(0)
                href = self.fix(m.group(1))
                play = self.getPlayUrl(href)
                if not play or play in seen:
                    continue
                seen.add(play)
                name = ''
                nm = re.search(r'<p[^>]*>([\s\S]*?)</p>', block, re.I)
                if nm: name = self.cleanName(nm.group(1))
                if not name: name = self.cleanName(href.split('/')[-1].split('.html')[0]) or '视频'
                pic = ''
                pm = re.search(r'<img[^>]+(?:data-original|data-src|src)=["\']([^"\']+)', block, re.I)
                if pm: pic = self.fix(pm.group(1))
                show_pic = self.picProxy(pic)
                vod_id = href + '|$|' + quote(name) + '|$|' + pic + '|$|' + play
                vods.append({'vod_id':vod_id, 'vod_name':self.decodeText(name), 'vod_pic':show_pic, 'vod_remarks':'直连'})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e))
            return []

    def getHtml(self, url):
        r = self.fetch(url, headers=self.headers, timeout=12)
        if hasattr(r, 'content'):
            return r.content.decode('utf-8', 'ignore')
        return getattr(r, 'text', '') or ''

    def fix(self, url):
        if not url: return ''
        return urljoin(self.host + '/', url.replace('&amp;', '&').strip())

    def decodeText(self, s):
        try:
            return ''.join([chr(ord(c) ^ 128) for c in (s or '')]).strip()
        except Exception:
            return s or ''

    def picProxy(self, url):
        if not url: return ''
        try:
            return self.getProxyUrl() + '&url=' + base64.b64encode(url.encode('utf-8')).decode('utf-8')
        except Exception:
            return url

    def decryptImage(self, data):
        key = b'2019ysapp7527'
        arr = bytearray(data or b'')
        l = min(100, len(arr))
        for i in range(l):
            arr[i] ^= key[i % len(key)]
        return bytes(arr)

    def cleanName(self, s):
        s = re.sub(r'<[^>]+>', ' ', s or '')
        s = unquote(s).replace('&nbsp;', ' ')
        s = re.sub(r'\s+', ' ', s).strip()
        return s[:80]

    def getQuery(self, url, key):
        try:
            q = parse_qs(urlparse(url).query)
            return (q.get(key) or [''])[0]
        except Exception:
            return ''

    def getPlayUrl(self, url):
        v = self.getQuery(url, 'v')
        if v: return v
        u = self.getQuery(url, 'url')
        if u and u.startswith('kk8-'):
            return 'https://m2cdn.playergo.top/' + u[4:] + '/playlist.m3u8'
        return u

    def isNoResultPage(self, txt):
        return bool(re.search(r'没有找到|暂无数据|搜索无结果|未找到|vodbox\s*0', txt or '', re.I))

    def isVideoFormat(self, url):
        return bool(re.search(r'\.(m3u8|mp4)(\?|$)', url or '', re.I))

    def makePlayHeader(self, url):
        return {'User-Agent': self.headers.get('User-Agent', 'Mozilla/5.0')}

    def manualVideoCheck(self):
        return True

    def liveContent(self, url):
        return None

    def localProxy(self, param):
        try:
            raw = param.get('url') or param.get('path') or ''
            if raw:
                img = base64.b64decode(raw).decode('utf-8')
                headers = {'User-Agent': self.headers.get('User-Agent','Mozilla/5.0'), 'Accept':'image/avif,image/webp,image/apng,image/*,*/*;q=0.8'}
                r = self.fetch(img, headers=headers, timeout=12)
                data = r.content if hasattr(r, 'content') else b''
                ctype0 = (getattr(r, 'headers', {}) or {}).get('content-type', '')
                if getattr(r, 'status_code', 200) != 200 or not data or b'<html' in data[:200].lower():
                    return [404, 'text/plain', b'']
                if data[:3] not in (b'\xff\xd8\xff',) and data[:8] != b'\x89PNG\r\n\x1a\n' and data[:6] not in (b'GIF87a', b'GIF89a'):
                    dec = self.decryptImage(data)
                    if dec[:3] == b'\xff\xd8\xff' or dec[:8] == b'\x89PNG\r\n\x1a\n' or dec[:6] in (b'GIF87a', b'GIF89a'):
                        data = dec
                if data[:8] == b'\x89PNG\r\n\x1a\n': ctype = 'image/png'
                elif data[:6] in (b'GIF87a', b'GIF89a'): ctype = 'image/gif'
                else: ctype = 'image/jpeg'
                return [200, ctype, data]
        except Exception as e:
            self.log('图片代理失败 %s' % e)
        return [404, 'text/plain', b'']

    def action(self, action):
        return None

    def destroy(self):
        return None

spider = Spider()