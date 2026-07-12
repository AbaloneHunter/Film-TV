# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 梦TV
# 主入口优先使用发布页：https://fuliwz.neocities.org/dizhi/
# 防走失：发布页 https://fuliwz.neocities.org/dizhi/；当前提示 https://www.mengtv22.click/；邮箱页面 Cloudflare protection 编码
import re, json, time, html
from urllib.parse import urljoin, quote, unquote
try:
    from base.spider import Spider as BaseSpider
except Exception:
    class BaseSpider(object):
        def fetch(self, url, headers=None, timeout=10):
            import requests, urllib3
            urllib3.disable_warnings()
            return requests.get(url, headers=headers, timeout=timeout, verify=False, allow_redirects=True)
        def log(self, msg): print(msg)

class Spider(BaseSpider):
    def __init__(self):
        self.publish = 'https://fuliwz.neocities.org/dizhi/'
        self.old_entry = 'https://meng1057.xyz'
        self.latest_hint = 'https://www.mengtv22.click'
        self.fallback = self.latest_hint
        self.host = self.latest_hint
        self.host_time = 0
        self.headers = {'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36','Accept':'text/html,*/*','Referer':self.latest_hint + '/'}
        self.groups = {
            '1': {'name':'视频一区','items':[('6','抖音视频'),('7','韩国主播'),('8','网红头条'),('9','网爆黑料'),('10','欧美无码'),('11','女优明星'),('12','SM调教'),('20','AV解说')]},
            '2': {'name':'视频二区','items':[('13','无码专区'),('14','麻豆传媒'),('15','制服诱惑'),('16','三级伦理'),('21','AI换脸'),('22','中文字幕'),('23','卡通动漫'),('24','欧美系列')]},
            '3': {'name':'视频三区','items':[('25','美女主播'),('26','国产自拍'),('27','熟女人妻'),('28','萝莉少女'),('29','女同性爱'),('30','多人群交'),('31','美乳巨乳'),('32','强奸乱伦')]}
        }
        self.classes = [{'type_id':k,'type_name':v['name']} for k,v in self.groups.items()]
        self.filters = {}
        for k,v in self.groups.items():
            vals = [{'n':'全部','v':''}] + [{'n':name,'v':tid} for tid,name in v['items']]
            self.filters[k] = [{'key':'cate','name':'分类','value':vals}]

    def getName(self): return '梦TV'
    def getDependence(self): return []
    def init(self, extend=''): return None

    def siteInfo(self):
        return {'current':self.host,'publish':self.publish,'latest_hint':self.latest_hint,'old_entry':self.old_entry,'backups':[self.latest_hint,self.old_entry],'email':'cloudflare-protected'}

    def getHost(self, force=False):
        if not force and self.host and time.time() - self.host_time < 1800:
            return self.host
        hosts = [self.latest_hint]
        try:
            txt = self.htmlRaw(self.publish, timeout=12)
            for x in re.findall(r'https?://(?:www\.)?(?:mengtv\d+\.click|meng\d+\.xyz)', txt or '', re.I):
                hosts.append(x.rstrip('/'))
        except Exception as e:
            self.log('发布页获取失败 %s %s' % (self.publish, e))
        hosts.append(self.old_entry)

        def score(x):
            x = x.rstrip('/')
            if x == self.latest_hint: return 100000
            if x == self.old_entry: return -1
            m = re.search(r'(?:mengtv|meng)(\d+)', x)
            return int(m.group(1)) if m else 0
        hosts = sorted(set([h.rstrip('/') for h in hosts if h]), key=score, reverse=True)
        for h in hosts:
            try:
                r = self.fetch(h + '/', headers=self.headers, timeout=10)
                txt = self.textOf(r)
                if getattr(r,'status_code',0) == 200 and ('梦TV' in txt or '/index.php/vod/detail/id/' in txt):
                    self.host = h
                    self.host_time = time.time()
                    self.headers['Referer'] = self.host + '/'
                    return self.host
            except Exception as e:
                if h == self.old_entry:
                    self.log('旧入口不可用 %s %s' % (h, e))
                else:
                    self.log('候选域名不可用 %s %s' % (h, e))
        self.host = self.latest_hint
        self.host_time = time.time()
        self.headers['Referer'] = self.host + '/'
        return self.host

    def homeContent(self, filter):
        return {'class':self.classes,'filters':self.filters if filter else {}}
    def getHomeContent(self, filter):
        return self.homeContent(filter)

    def homeVideoContent(self):
        return {'list':self.parseList(self.getHost() + '/')}

    def categoryContent(self, tid, pg, filter, extend):
        self.getHost()
        pg = str(pg or '1')
        real_tid = (extend or {}).get('cate') or str(tid)
        url = self.host + ('/index.php/vod/type/id/%s.html' % real_tid if pg == '1' else '/index.php/vod/type/id/%s/page/%s.html' % (real_tid, pg))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'pagecount':999999 if vods else int(pg),'limit':40,'total':999999 if vods else 0}

    def detailContent(self, ids):
        self.getHost()
        raw = str(ids[0])
        ps = raw.split('|$|')
        vid = ps[0]
        old_name = unquote(ps[1]) if len(ps) > 1 else ''
        old_pic = ps[2] if len(ps) > 2 else ''
        title, pic, play = old_name, old_pic, ''
        try:
            txt = self.htmlRaw(vid)
            title = self.clean(self.find1(txt, r'<h1[^>]*>([\s\S]*?)</h1>') or self.find1(txt, r'<title[^>]*>([\s\S]*?)(?:详情介绍|在线观看|-\s*梦TV)')) or title
            pic = self.fix(self.find1(txt, r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)') or self.find1(txt, r'<img[^>]+(?:data-src|data-original|src)=["\']([^"\']+)["\'][^>]*alt=["\']%s' % re.escape(title)) or pic)
            play = self.fix(self.find1(txt, r'href=["\']([^"\']*?/index\.php/vod/play/id/\d+[^"\']*)["\']'))
        except Exception as e:
            self.log('详情解析失败 %s' % e)
        if not title: title = '视频'
        play_url = '播放$' + (play or vid)
        return {'list':[{'vod_id':vid,'vod_name':title,'vod_pic':pic,'type_name':'','vod_year':'','vod_area':'','vod_remarks':'','vod_actor':'','vod_director':'','vod_content':title,'vod_play_from':'高清','vod_play_url':play_url}]}

    def searchContent(self, key, quick, pg='1'):
        self.getHost()
        pg = str(pg or '1')
        url = self.host + ('/index.php/vod/search.html?wd=%s' % quote(key or '') if pg == '1' else '/index.php/vod/search/page/%s/wd/%s.html' % (pg, quote(key or '')))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'total':len(vods)}

    def playerContent(self, flag, id, vipFlags):
        self.getHost()
        url = id
        try:
            if not self.isMedia(url):
                txt = self.htmlRaw(url)
                m = re.search(r'player_\w+\s*=\s*(\{[^\n<]+\})', txt or '', re.I)
                if m:
                    try:
                        j = json.loads(m.group(1)); url = (j.get('url') or url).replace('\\/','/')
                    except Exception: pass
                if not self.isMedia(url):
                    url = html.unescape(self.find1(txt, r'(https?://[^"\'<>\s]+?\.(?:m3u8|mp4)[^"\'<>\s]*)') or url)
        except Exception as e:
            self.log('播放解析失败 %s' % e)
        if self.isMedia(url):
            return {'parse':0,'url':url,'header':self.playHeader(url)}
        return {'parse':1,'url':id,'header':self.headers}

    def parseList(self, url):
        try:
            txt = self.htmlRaw(url)
            vods, seen = [], set()
            blocks = re.findall(r'(<a[^>]+href=["\']([^"\']*?/index\.php/vod/detail/id/\d+\.html)["\'][\s\S]*?</a>)', txt, re.I)
            for block, href in blocks:
                href = self.fix(href)
                if href in seen: continue
                seen.add(href)
                name = self.clean(self.find1(block, r'<img[^>]+(?:alt|title)=["\']([^"\']+)') or self.find1(block, r'title=["\']([^"\']+)'))
                pic = self.fix(self.find1(block, r'<img[^>]+data-original=["\']([^"\']+)') or self.find1(block, r'<img[^>]+data-src=["\']([^"\']+)') or self.find1(block, r'<img[^>]+src=["\']([^"\']+)'))
                if not name or '梦TV' in name: continue
                remark = self.clean(self.find1(block, r'<span[^>]+class=["\'][^"\']*(?:note|duration|remarks)[^"\']*["\'][^>]*>([\s\S]*?)</span>')) or '详情'
                vods.append({'vod_id':href+'|$|'+quote(name)+'|$|'+pic,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e)); return []

    def htmlRaw(self, url, timeout=15):
        r = self.fetch(url, headers=self.headers, timeout=timeout)
        return self.textOf(r)
    def textOf(self, r):
        if hasattr(r,'content'):
            return r.content.decode('utf-8','ignore')
        return getattr(r,'text','') or ''
    def fix(self, u):
        if not u: return ''
        return urljoin(self.host + '/', str(u).replace('&amp;','&').strip())
    def find1(self, txt, pat):
        m = re.search(pat, txt or '', re.I); return m.group(1) if m else ''
    def clean(self, s):
        s = html.unescape(str(s or ''))
        s = re.sub(r'<[^>]+>', ' ', s)
        return re.sub(r'&nbsp;|\s+', ' ', s).strip()[:120]
    def isMedia(self, url): return bool(re.search(r'\.(m3u8|mp4)(\?|$)', url or '', re.I))
    def playHeader(self, url): return {'User-Agent':'Mozilla/5.0','Accept':'*/*'}
    def localProxy(self, param): return [404,'text/plain',b'']
    def manualVideoCheck(self): return True
    def liveContent(self, url): return None
    def action(self, action): return None
    def destroy(self): return None

spider = Spider()