# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 千媚宫
# 主入口优先使用发布页：https://qmgdizhi2.life
# 防走失：发布页 https://qmgdizhi2.life；导航入口 https://qmg09.life/qmg；当前段 qmg198.cc~qmg202.cc；邮箱 qmg10086@gmail.com
import re, json, time, html
from urllib.parse import urljoin, quote
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
        self.publish = 'https://qmgdizhi2.life'
        self.nav = 'https://qmg09.life/qmg'
        self.fallback = 'https://qmg202.cc'
        self.host = self.fallback
        self.host_time = 0
        self.email = 'qmg10086@gmail.com'
        self.headers = {'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36','Accept':'text/html,*/*','Referer':self.fallback + '/'}
        self.classes = [
            {'type_id':'1','type_name':'国产视频'}, {'type_id':'2','type_name':'中文字幕'},
            {'type_id':'3','type_name':'无码视频'}, {'type_id':'4','type_name':'欧美视频'},
            {'type_id':'5','type_name':'成人动漫'}
        ]
        vals = [{'n':'分类','v':''},{'n':'最新','v':'new'},{'n':'热门','v':'hot'},{'n':'点赞','v':'like'}]
        self.filters = {c['type_id']:[{'key':'by','name':'排序','value':vals}] for c in self.classes}

    def getName(self): return '千媚宫'
    def getDependence(self): return []

    def init(self, extend=''):
        return None

    def siteInfo(self):
        return {'current':self.host,'publish':self.publish,'nav':self.nav,'backups':['https://qmg198.cc~https://qmg202.cc'],'email':self.email}

    def getHost(self, force=False):
        if not force and self.host and time.time() - self.host_time < 1800:
            return self.host
        hosts = []
        for u in [self.publish, self.nav]:
            try:
                txt = self.htmlRaw(u, nohost=True)
                for n in re.findall(r'https?://(?:www\.)?qmg(\d+)\.cc', txt or '', re.I):
                    hosts.append('https://qmg%s.cc' % n)
            except Exception as e:
                self.log('发布页获取失败 %s %s' % (u, e))
        def num(x):
            m = re.search(r'qmg(\d+)\.cc', x); return int(m.group(1)) if m else 0
        hosts = sorted(set(hosts), key=num, reverse=True)
        for h in hosts + [self.fallback]:
            try:
                r = self.fetch(h + '/', headers=self.headers, timeout=10)
                txt = self.textOf(r)
                if getattr(r, 'status_code', 0) == 200 and ('千媚宫' in txt or '/vodplay/' in txt):
                    self.host = h.rstrip('/')
                    self.host_time = time.time()
                    self.headers['Referer'] = self.host + '/'
                    return self.host
            except Exception:
                pass
        self.host = self.fallback
        self.host_time = time.time()
        return self.host

    def homeContent(self, filter):
        # 首页分类必须快速稳定返回，不能依赖发布页网络请求，否则部分壳会吞异常显示 class=[]
        return {'class':self.classes,'filters':self.filters if filter else {}}

    def getHomeContent(self, filter):
        return self.homeContent(filter)

    def homeVideoContent(self):
        return {'list':self.parseList(self.getHost() + '/')}

    def categoryContent(self, tid, pg, filter, extend):
        self.getHost()
        pg = str(pg or '1')
        by = (extend or {}).get('by') or ''
        if by in ['new','hot','like']:
            url = self.host + ('/label/%s/' % by if pg == '1' else '/label/%s/page/%s/' % (by, pg))
        else:
            url = self.host + ('/vodtype/%s/' % tid if pg == '1' else '/vodtype/%s-%s/' % (tid, pg))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'pagecount':999999 if vods else int(pg),'limit':25,'total':999999 if vods else 0}

    def detailContent(self, ids):
        self.getHost()
        raw = str(ids[0])
        ps = raw.split('|$|')
        vid = ps[0]
        old_name = self.clean(ps[1]) if len(ps) > 1 else ''
        old_pic = ps[2] if len(ps) > 2 else ''
        url = vid if vid.startswith('http') else self.host + vid
        txt = ''
        name, pic, play = old_name, old_pic, ''
        try:
            txt = self.htmlRaw(url)
            name = self.clean(self.find1(txt, r'<title[^>]*>([\s\S]*?)(?:\s*-\s*千媚宫|</title>)')) or name
            pic = self.fix(self.find1(txt, r'<img[^>]+(?:img|data-src|src)=["\']([^"\']+)["\'][^>]*alt=["\']%s' % re.escape(name)) or pic)
            play = self.extractFromText(txt)
        except Exception as e:
            self.log('详情解析失败 %s' % e)
        play_url = '播放$' + (play or url)
        return {'list':[{'vod_id':url,'vod_name':name or '视频','vod_pic':pic,'type_name':'','vod_year':'','vod_area':'','vod_remarks':'','vod_actor':'','vod_director':'','vod_content':name or '','vod_play_from':'高清','vod_play_url':play_url}]}

    def searchContent(self, key, quick, pg='1'):
        self.getHost()
        pg = str(pg or '1')
        url = self.host + ('/vod/search/?wd=%s' % quote(key or '') if pg == '1' else '/vod/search/page/%s/?wd=%s' % (pg, quote(key or '')))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'total':len(vods)}

    def playerContent(self, flag, id, vipFlags):
        self.getHost()
        url = id
        try:
            if not self.isMedia(url):
                txt = self.htmlRaw(url if url.startswith('http') else self.host + url)
                url = self.extractFromText(txt) or url
        except Exception as e:
            self.log('播放解析失败 %s' % e)
        if self.isMedia(url):
            return {'parse':0,'url':url,'header':self.playHeader(url)}
        return {'parse':1,'url':id,'header':self.headers}

    def parseList(self, url):
        try:
            txt = self.htmlRaw(url)
            vods, seen = [], set()
            blocks = re.findall(r'(<a[^>]+href=["\']([^"\']*?/vodplay/\d+-\d+-\d+/)["\'][\s\S]*?</a>)', txt, re.I)
            for block, href in blocks:
                href = self.fix(href)
                if href in seen: continue
                seen.add(href)
                name = self.clean(self.find1(block, r'<img[^>]+(?:img|data-src|src)=["\'][^"\']+["\'][^>]*alt=["\']([^"\']+)') or self.find1(block, r'<li[^>]+class=["\']title["\'][^>]*>([\s\S]*?)</li>'))
                pic = self.fix(self.find1(block, r'<img[^>]+(?:img|data-src|src)=["\']([^"\']+)'))
                remark = self.clean(self.find1(block, r'<span[^>]+class=["\']note["\'][^>]*>([\s\S]*?)</span>') or self.find1(block, r'<span[^>]+class=["\']notes["\'][^>]*>([\s\S]*?)</span>'))
                if not name or '千媚宫成人' in name or '娱乐' in name or '葡京' in name or '开元' in name: continue
                vods.append({'vod_id':href+'|$|'+name+'|$|'+pic,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e)); return []

    def extractFromText(self, txt):
        m = re.search(r'player_\w+\s*=\s*(\{[^<\n]+\})', txt or '', re.I)
        if m:
            try:
                j = json.loads(m.group(1))
                u = (j.get('url') or '').replace('\\/','/')
                if self.isMedia(u): return u
            except Exception:
                pass
        return html.unescape(self.find1(txt, r'(https?://[^"\'<>\s]+?\.m3u8[^"\'<>\s]*)') or '')

    def htmlRaw(self, url, nohost=False):
        r = self.fetch(url, headers=self.headers, timeout=15)
        return self.textOf(r)
    def textOf(self, r):
        if hasattr(r, 'content'):
            return r.content.decode('utf-8','ignore')
        return getattr(r, 'text', '') or ''
    def clean(self, s):
        s = html.unescape(str(s or ''))
        s = re.sub(r'<[^>]+>', ' ', s)
        return re.sub(r'&nbsp;|\s+', ' ', s).strip()[:120]
    def fix(self, u):
        if not u: return ''
        return urljoin(self.host + '/', str(u).replace('&amp;','&').strip())
    def find1(self, txt, pat):
        m = re.search(pat, txt or '', re.I); return m.group(1) if m else ''
    def isMedia(self, url): return bool(re.search(r'\.(m3u8|mp4)(\?|$)', url or '', re.I))
    def playHeader(self, url): return {'User-Agent':'Mozilla/5.0','Accept':'*/*'}
    def localProxy(self, param): return [404,'text/plain',b'']
    def manualVideoCheck(self): return True
    def liveContent(self, url): return None
    def action(self, action): return None
    def destroy(self): return None

spider = Spider()