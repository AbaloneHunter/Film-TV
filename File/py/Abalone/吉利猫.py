# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 吉利猫 jilim25.life
# 防走失：导航入口 https://jilim.life/jilim；永久域名 https://gckgg.com；备用 jlm100.cc~jlm174.cc；邮箱 caishenfei718@gmail.com
import re, html
from urllib.parse import urljoin, quote, unquote
try:
    from base.spider import Spider as BaseSpider
except Exception:
    class BaseSpider(object):
        def fetch(self, url, headers=None, timeout=10):
            import requests, urllib3
            urllib3.disable_warnings()
            return requests.get(url, headers=headers, timeout=timeout, verify=False)
        def log(self, msg): print(msg)

class Spider(BaseSpider):
    def getName(self): return '吉利猫'
    def getDependence(self): return []

    def init(self, extend=''):
        self.host = 'https://jilim25.life'
        self.nav = 'https://jilim.life/jilim'
        self.permanent = 'https://gckgg.com'
        self.backups = ['https://jlm174.cc', 'https://jlm100.cc~https://jlm174.cc']
        self.email = 'caishenfei718@gmail.com'
        self.headers = {'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36','Accept':'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8','Referer':self.host + '/'}
        self.classes = [
            {'type_id':'46','type_name':'日韩自拍'}, {'type_id':'43','type_name':'色情主播'},
            {'type_id':'44','type_name':'国产AV'}, {'type_id':'59','type_name':'乱伦侵犯'},
            {'type_id':'47','type_name':'日本无码'}, {'type_id':'50','type_name':'无码字幕'},
            {'type_id':'49','type_name':'色情动漫'}, {'type_id':'48','type_name':'有码字幕'},
            {'type_id':'51','type_name':'欧美AV'}, {'type_id':'52','type_name':'18禁重口味'},
            {'type_id':'53','type_name':'偷拍偷窥'}, {'type_id':'54','type_name':'网爆吃瓜'},
            {'type_id':'55','type_name':'传媒AV片'}, {'type_id':'56','type_name':'探花约炮'},
            {'type_id':'57','type_name':'三级伦理'}, {'type_id':'58','type_name':'AV解说'}
        ]
        vals = [{'n':'默认','v':''},{'n':'最近更新','v':'time'},{'n':'当前最热','v':'hits'},{'n':'本周最热','v':'hits_week'},{'n':'本月最热','v':'hits_month'}]
        self.filters = {c['type_id']:[{'key':'by','name':'排序','value':vals}] for c in self.classes}

    def siteInfo(self):
        return {'current':self.host,'nav':self.nav,'permanent':self.permanent,'backups':self.backups,'email':self.email}
    def homeContent(self, filter): return {'class':self.classes,'filters':self.filters if filter else {}}
    def homeVideoContent(self): return {'list':self.parseList(self.host + '/')}

    def categoryContent(self, tid, pg, filter, extend):
        pg = str(pg or '1'); by = (extend or {}).get('by') or ''
        if by:
            url = self.host + ('/index.php/vod/show/by/%s/id/%s.html' % (by, tid) if pg == '1' else '/index.php/vod/show/by/%s/id/%s/page/%s.html' % (by, tid, pg))
        else:
            url = self.host + ('/index.php/vod/type/id/%s.html' % tid if pg == '1' else '/index.php/vod/type/id/%s/page/%s.html' % (tid, pg))
        vods = self.parseList(url)
        return {'list':vods,'page':int(pg),'pagecount':999999 if vods else int(pg),'limit':24,'total':999999 if vods else 0}

    def detailContent(self, ids):
        vid = ids[0]; title, pic, play = '', '', ''
        if '|$|' in vid:
            ps = vid.split('|$|'); vid = ps[0]
            title = unquote(ps[1]) if len(ps)>1 else ''
            pic = ps[2] if len(ps)>2 else ''
            play = ps[3] if len(ps)>3 else ''
        if not title or not play:
            try:
                txt = self.html(vid)
                title = title or self.clean(self.find1(txt, r'<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)') or self.find1(txt, r'<title[^>]*>([\s\S]*?)(?:-|在线观看|播放)'))
                pic = pic or self.find1(txt, r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)')
                play = play or self.cleanPlay(self.find1(txt, r'data-url=["\']([^"\']*?https?://[^"\']+?\.m3u8[^"\']*)') or self.find1(txt, r'(https?://[^"\'<>\s]+?\.m3u8[^"\'<>\s]*)'))
            except Exception as e: self.log('详情解析失败 %s' % e)
        if not title: title = '视频'
        play_url = '播放$' + (play or vid)
        return {'list':[{'vod_id':vid,'vod_name':title,'vod_pic':self.fix(pic),'type_name':'','vod_year':'','vod_area':'','vod_remarks':'直连','vod_actor':'','vod_director':'','vod_content':title,'vod_play_from':'高清','vod_play_url':play_url}]}

    def searchContent(self, key, quick, pg='1'):
        pg = str(pg or '1')
        path = '/index.php/vod/search/wd/%s.html' % quote(key or '') if pg == '1' else '/index.php/vod/search/page/%s/wd/%s.html' % (pg, quote(key or ''))
        vods = self.parseList(self.host + path)
        return {'list':vods,'page':int(pg),'total':len(vods)}

    def playerContent(self, flag, id, vipFlags):
        url = id
        try:
            if not self.isMedia(url):
                txt = self.html(id)
                url = self.cleanPlay(self.find1(txt, r'data-url=["\']([^"\']*?https?://[^"\']+?\.m3u8[^"\']*)') or self.find1(txt, r'(https?://[^"\'<>\s]+?\.m3u8[^"\'<>\s]*)')) or id
        except Exception as e: self.log('播放解析失败 %s' % e)
        if self.isMedia(url): return {'parse':0,'url':url,'header':self.playHeader(url)}
        return {'parse':1,'url':id,'header':self.headers}

    def parseList(self, url):
        try:
            txt = self.html(url)
            vods, seen = [], set()
            blocks = re.findall(r'<li>\s*<div[^>]+class=["\'][^"\']*video-item[^"\']*["\'][\s\S]*?</li>', txt, re.I)
            if not blocks:
                blocks = re.findall(r'<div[^>]+class=["\'][^"\']*video-item[^"\']*["\'][\s\S]*?</div>\s*</li>', txt, re.I)
            for block in blocks:
                href = self.fix(self.find1(block, r'<a[^>]+href=["\']([^"\']*?/index\.php/vod/play/id/\d+[^"\']*)'))
                if not href or href in seen: continue
                name = self.clean(self.find1(block, r'<img[^>]+alt=["\']([^"\']+)') or self.find1(block, r'<a[^>]+class=["\'][^"\']*line-clamp[^"\']*["\'][^>]*>([\s\S]*?)</a>'))
                pic = self.fix(self.find1(block, r'<img[^>]*?\sdata-src=["\']([^"\']+)') or self.find1(block, r'<img[^>]*?\ssrc=["\']([^"\']+)'))
                play = self.cleanPlay(self.find1(block, r'data-url=["\']([^"\']*?https?://[^"\']+?\.m3u8[^"\']*)'))
                remark = self.clean(self.find1(block, r'<div[^>]+class=["\'][^"\']*text-sm[^"\']*["\'][^>]*>([\s\S]*?)</div>')) or '直连'
                if not name: continue
                seen.add(href)
                vods.append({'vod_id':href+'|$|'+quote(name)+'|$|'+pic+'|$|'+play,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e)); return []

    def html(self, url):
        r = self.fetch(url, headers=self.headers, timeout=15)
        return r.content.decode('utf-8','ignore') if hasattr(r,'content') else getattr(r,'text','')
    def cleanPlay(self, s):
        s = (s or '').replace('&amp;','&').strip()
        if '$' in s: s = s.split('$')[-1]
        return s
    def fix(self, url): return urljoin(self.host + '/', (url or '').replace('&amp;','&').strip()) if url else ''
    def find1(self, txt, pat):
        m = re.search(pat, txt or '', re.I); return m.group(1) if m else ''
    def clean(self, s):
        s = html.unescape(s or '')
        s = re.sub(r'<[^>]+>', ' ', s); s = re.sub(r'&nbsp;|\s+', ' ', s).strip(); return s[:120]
    def isMedia(self, url): return bool(re.search(r'\.(m3u8|mp4)(\?|$)', url or '', re.I))
    def playHeader(self, url): return {'User-Agent':'Mozilla/5.0','Accept':'*/*'}
    def localProxy(self, param): return [404,'text/plain',b'']
    def manualVideoCheck(self): return True
    def liveContent(self, url): return None
    def action(self, action): return None
    def destroy(self): return None

spider = Spider()