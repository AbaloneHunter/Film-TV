# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 大香蕉
# 永久入口：https://www.xiangjiao.info/；当前自动跳转域：https://www.xiangjiao2.com
import re, json, time, html
from urllib.parse import urljoin, quote, unquote
import requests, urllib3
urllib3.disable_warnings()
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
        self.permanent = 'https://www.xiangjiao.info'
        self.latest_hint = 'https://www.xiangjiao2.com'
        self.host = self.latest_hint
        self.host_time = 0
        self.headers = {'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36','Accept':'text/html,*/*','Referer':self.latest_hint + '/'}
        self.classes = [
            {'type_id':'6','type_name':'国产传媒'}, {'type_id':'7','type_name':'国产精品'},
            {'type_id':'3','type_name':'AV明星'}, {'type_id':'9','type_name':'日韩无码'},
            {'type_id':'10','type_name':'动漫精品'}, {'type_id':'11','type_name':'自拍偷拍'},
            {'type_id':'12','type_name':'伦理影片'}, {'type_id':'13','type_name':'中文字幕'},
            {'type_id':'35','type_name':'欧美精品'}, {'type_id':'14','type_name':'人妻系列'},
            {'type_id':'15','type_name':'制服诱惑'}, {'type_id':'33','type_name':'熟女少妇'},
            {'type_id':'20','type_name':'巨乳系列'}, {'type_id':'26','type_name':'明星换脸'},
            {'type_id':'27','type_name':'AV解说'}, {'type_id':'30','type_name':'精品网红'}
        ]
        self.filters = {c['type_id']:[{'key':'sort','name':'排序','value':[{'n':'最新','v':''}]}] for c in self.classes}
        self.home_ready = False

    def getName(self): return '大香蕉'
    def getDependence(self): return []
    def init(self, extend=''):
        # 初始化和首页分类入口不访问网络；只返回本地写死分类，避免壳子首页卡顿或 null
        return None

    def siteInfo(self):
        return {'current':self.host,'permanent':self.permanent,'latest_hint':self.latest_hint,'api':'closed','route':'maccms-html'}

    def getHost(self, force=False):
        if not force and self.host and time.time() - self.host_time < 1800:
            return self.host
        hosts = [self.latest_hint]
        try:
            r = self.req(self.permanent + '/', timeout=12)
            final = getattr(r, 'url', '') or ''
            if final: hosts.insert(0, final.rstrip('/'))
            txt = self.textOf(r)
            for x in re.findall(r'https?://(?:www\.)?xiangjiao\d*\.com|https?://(?:www\.)?xiangjiao\.info', (txt or '') + ' ' + final, re.I):
                hosts.append(x.rstrip('/'))
        except Exception as e:
            self.log('永久入口探测失败 %s %s' % (self.permanent, e))
        # 当前域名可能换号，扩大数字段自动探测；优先 www，再裸域
        for i in range(2, 13):
            hosts.append('https://www.xiangjiao%s.com' % i)
            hosts.append('https://xiangjiao%s.com' % i)
        seen = []
        for h in hosts + [self.permanent]:
            h = (h or '').rstrip('/')
            if h and h not in seen: seen.append(h)
        for h in seen:
            try:
                r = self.req(h + '/', timeout=10)
                txt = self.textOf(r)
                final = (getattr(r,'url','') or h).rstrip('/')
                if getattr(r,'status_code',0) == 200 and ('/index.php/vod/type/id/' in txt or '/index.php/vod/detail/id/' in txt):
                    self.host = final
                    self.headers['Referer'] = self.host + '/'
                    self.host_time = time.time()
                    return self.host
            except Exception as e:
                self.log('候选域名不可用 %s %s' % (h, e))
        self.host = self.latest_hint
        self.host_time = time.time()
        self.headers['Referer'] = self.host + '/'
        return self.host

    def refreshHome(self, force=False):
        if self.home_ready and not force:
            return
        try:
            host = self.getHost(force=force)
            txt = self.htmlRaw(host + '/', timeout=12)
            classes, seen = [], set()
            for href, tid, name in re.findall(r'href=["\']([^"\']*?/index\.php/vod/type/id/(\d+)\.html)["\'][^>]*>([\s\S]{0,120}?)</a>', txt, re.I):
                nm = self.clean(name)
                if not nm or nm == '更多' or tid in seen: continue
                if any(x in nm for x in ['首页','留言','排行','专题']): continue
                seen.add(tid)
                classes.append({'type_id':tid,'type_name':nm})
            if classes:
                self.classes = classes
                self.filters = {c['type_id']:[{'key':'sort','name':'排序','value':[{'n':'最新','v':''}]}] for c in self.classes}
                self.home_ready = True
        except Exception as e:
            self.log('动态分类获取失败 %s' % e)

    def homeContent(self, filter):
        # 首页分类必须零网络：分类/筛选写死；动态域名只在取列表、搜索、详情、播放时执行
        return {'class':self.classes,'filters':self.filters if filter else {}}
    def getHomeContent(self, filter):
        return self.homeContent(filter)

    def homeVideoContent(self):
        return {'list':self.parseList(self.getHost() + '/')}

    def categoryContent(self, tid, pg, filter, extend):
        self.getHost()
        pg = str(pg or '1')
        url = self.host + ('/index.php/vod/type/id/%s.html' % tid if pg == '1' else '/index.php/vod/type/id/%s/page/%s.html' % (tid, pg))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'pagecount':999999 if vods else int(pg),'limit':48,'total':999999 if vods else 0}

    def detailContent(self, ids):
        self.getHost()
        raw = str(ids[0]); ps = raw.split('|$|')
        vid = ps[0]
        old_name = unquote(ps[1]) if len(ps) > 1 else ''
        old_pic = ps[2] if len(ps) > 2 else ''
        title, pic, play_links = old_name, old_pic, []
        try:
            txt = self.htmlRaw(vid)
            title = self.clean(self.find1(txt, r'<h1[^>]*>([\s\S]*?)</h1>') or self.find1(txt, r'<title[^>]*>([\s\S]*?)(?:_|-|\|)')) or title
            pic = self.fix(self.find1(txt, r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)') or self.find1(txt, r'<img[^>]+(?:data-original|data-src|src)=["\']([^"\']+)["\'][^>]*alt=["\']%s' % re.escape(title)) or pic)
            for u in re.findall(r'href=["\']([^"\']*?/index\.php/vod/play/id/\d+[^"\']*)["\']', txt, re.I):
                u = self.fix(u)
                if u not in play_links: play_links.append(u)
        except Exception as e:
            self.log('详情解析失败 %s' % e)
        if not title: title = '视频'
        if not play_links: play_links = [vid]
        play_url = '#'.join(['第%d集$%s' % (i+1, u) for i,u in enumerate(play_links)])
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
                        j = json.loads(m.group(1)); url = html.unescape((j.get('url') or url).replace('\\/','/'))
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
            li_blocks = re.findall(r'(<li\b[\s\S]*?</li>)', txt, re.I)
            blocks = []
            for li in li_blocks:
                h = self.find1(li, r'href=["\']([^"\']*?/index\.php/vod/detail/id/\d+\.html)["\']')
                if h: blocks.append((li, h))
            if not blocks:
                for a,h in re.findall(r'(<a[^>]+href=["\']([^"\']*?/index\.php/vod/detail/id/\d+\.html)["\'][\s\S]*?</a>)', txt, re.I):
                    blocks.append((a,h))
            for block, href in blocks:
                href = self.fix(href)
                if href in seen: continue
                seen.add(href)
                name = self.clean(self.find1(block, r'<h5[\s\S]*?<a[^>]+title=["\']([^"\']+)') or self.find1(block, r'<img[^>]+alt=["\']([^"\']+)') or self.find1(block, r'title=["\']([^"\']+)'))
                pic = self.fix(self.find1(block, r'<img[^>]+data-original=["\']([^"\']+)') or self.find1(block, r'<img[^>]+data-src=["\']([^"\']+)') or self.find1(block, r'<img[^>]+src=["\']([^"\']+)'))
                remark = self.clean(self.find1(block, r'<p[^>]*>([\s\S]*?)</p>')) or '详情'
                if not name or any(x in name for x in ['娱乐','导航','推荐','站长','棋牌','广告']): continue
                if re.search(r'/template/.*/tp/|hf\d+\.gif|fangtutu|web\d+\.', pic or '', re.I): continue
                vods.append({'vod_id':href+'|$|'+quote(name)+'|$|'+pic,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e)); return []

    def req(self, url, timeout=15):
        # 避免真实 Jar 环境 BaseSpider.fetch 的 SSL/连接策略影响，站点请求直接关闭证书校验
        return requests.get(url, headers=self.headers, timeout=timeout, verify=False, allow_redirects=True)

    def htmlRaw(self, url, timeout=15):
        r = self.req(url, timeout=timeout)
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