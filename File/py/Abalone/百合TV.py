# -*- coding: utf-8 -*-
# FongMi/TVBox Python Spider - 百合TV
# 防走失：发布/防封 https://bh44.top；永久域名 qq.com.bhtv20.top；邮箱 niso000aa@gmail.com
import re, time, html
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
        self.publish = 'https://bh44.top'
        self.permanent = 'https://qq.com.bhtv20.top'
        self.fallback = 'https://qq.com.8mpm0di.top'
        self.nav = 'https://qq.com.bh31y0.top/'
        self.host = self.fallback
        self.host_time = 0
        self.email = 'niso000aa@gmail.com'
        self.headers = {'User-Agent':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36','Accept':'text/html,*/*','Referer':self.fallback + '/'}
        self.classes = [
            {'type_id':'1','type_name':'视频一区'}, {'type_id':'2','type_name':'视频二区'},
            {'type_id':'3','type_name':'视频三区'}, {'type_id':'4','type_name':'视频四区'},
            {'type_id':'5','type_name':'视频五区'}
        ]
        self.filters = {
            '1':[{'key':'cid','name':'分类','value':[{'n':'全部','v':''},{'n':'国产视频','v':'10'},{'n':'国产传媒','v':'11'},{'n':'国产探花','v':'12'},{'n':'极品学生','v':'6'},{'n':'野战户外','v':'7'},{'n':'AI合成','v':'8'},{'n':'中文字幕','v':'9'},{'n':'熟女人妻','v':'13'},{'n':'乱伦背德','v':'14'},{'n':'巨乳美乳','v':'15'},{'n':'制服黑丝','v':'16'},{'n':'自拍偷拍','v':'17'},{'n':'少女萝莉','v':'18'},{'n':'韩国主播','v':'19'},{'n':'卡通动漫','v':'20'},{'n':'伦理三级','v':'21'},{'n':'日本无码','v':'99'},{'n':'口爆颜射','v':'100'},{'n':'解说AV','v':'101'},{'n':'欧美精品','v':'102'},{'n':'日本有码','v':'103'},{'n':'同性女优','v':'105'},{'n':'重口调教','v':'106'}]}],
            '2':[{'key':'cid','name':'分类','value':[{'n':'全部','v':''},{'n':'亚洲情色','v':'22'},{'n':'国产主播','v':'23'},{'n':'国产自拍','v':'24'},{'n':'无码专区','v':'25'},{'n':'欧美性爱','v':'26'},{'n':'熟女人妻','v':'27'},{'n':'强奸乱伦','v':'28'},{'n':'巨乳美乳','v':'29'},{'n':'中文字幕','v':'30'},{'n':'制服诱惑','v':'31'},{'n':'女同性恋','v':'32'},{'n':'卡通动画','v':'33'},{'n':'丝袜长腿','v':'34'},{'n':'少女萝莉','v':'35'},{'n':'重口色情','v':'36'},{'n':'人兽性交','v':'37'},{'n':'福利姬','v':'107'}]}],
            '3':[{'key':'cid','name':'分类','value':[{'n':'全部','v':''},{'n':'国产精品','v':'38'},{'n':'福利姬','v':'39'},{'n':'精品三级','v':'40'},{'n':'主播大秀','v':'41'},{'n':'抖阴视频','v':'42'},{'n':'国模私拍','v':'43'},{'n':'水果派','v':'44'},{'n':'颜射瞬间','v':'45'},{'n':'女神学生','v':'46'},{'n':'美熟少妇','v':'47'},{'n':'娇妻素人','v':'48'},{'n':'空姐模特','v':'49'},{'n':'国产乱伦','v':'50'},{'n':'自慰群交','v':'51'},{'n':'野合车震','v':'52'},{'n':'职场同事','v':'95'},{'n':'国产名人','v':'96'},{'n':'网曝门事件','v':'97'},{'n':'小鸟酱专题','v':'98'}]}],
            '4':[{'key':'cid','name':'分类','value':[{'n':'全部','v':''},{'n':'日韩无码','v':'53'},{'n':'强奸乱伦','v':'54'},{'n':'欧美精品','v':'55'},{'n':'国产精品','v':'56'},{'n':'人妻系列','v':'57'},{'n':'3P合辑','v':'58'},{'n':'SM重味','v':'59'},{'n':'自慰系列','v':'60'},{'n':'自拍偷拍','v':'61'},{'n':'制服诱惑','v':'62'},{'n':'日韩精品','v':'63'},{'n':'伦理影片','v':'64'},{'n':'动漫精品','v':'65'},{'n':'中文字幕','v':'66'},{'n':'有码视频','v':'67'},{'n':'口交视频','v':'68'},{'n':'颜射系列','v':'69'},{'n':'巨乳系列','v':'70'},{'n':'教师学生','v':'71'},{'n':'大秀视频','v':'72'}]}],
            '5':[{'key':'cid','name':'分类','value':[{'n':'全部','v':''},{'n':'国产自拍','v':'73'},{'n':'欧美极品','v':'74'},{'n':'日韩无码','v':'75'},{'n':'日韩有码','v':'76'},{'n':'中文字幕','v':'77'},{'n':'动漫精品','v':'78'},{'n':'极骚萝莉','v':'79'},{'n':'人妖视频','v':'80'},{'n':'重咸口味','v':'81'},{'n':'三级自慰','v':'82'},{'n':'强奸乱伦','v':'83'},{'n':'擂台格斗','v':'84'},{'n':'辣椒GIGA','v':'85'},{'n':'HEYZO','v':'86'},{'n':'独家DMM','v':'87'},{'n':'HEY诱惑','v':'88'},{'n':'童颜巨乳','v':'89'},{'n':'高潮喷吹','v':'90'},{'n':'激情口交','v':'91'},{'n':'绝美少女','v':'92'},{'n':'首次亮相','v':'93'}]}]
        }
        self.home_ready = True

    def getName(self): return '百合TV'
    def getDependence(self): return []
    def init(self, extend=''):
        # 初始化和首页分类入口不访问网络；分类/筛选本地写死，避免壳子首页卡顿或 null
        return None

    def siteInfo(self):
        return {'current':self.host,'publish':self.publish,'permanent':self.permanent,'nav':self.nav,'email':self.email}

    def getHost(self, force=False):
        if not force and self.host and time.time() - self.host_time < 1800:
            return self.host
        candidates = []
        for u in [self.publish, self.nav, self.fallback, self.permanent]:
            try:
                r = self.req(u, timeout=10)
                final = getattr(r, 'url', '') or u
                txt = self.textOf(r)
                for x in re.findall(r'https?://qq\.com\.[a-z0-9]+\.top', txt + ' ' + final, re.I):
                    candidates.append(x.rstrip('/'))
                for x in re.findall(r'https?://bh44\.top', txt + ' ' + final, re.I):
                    candidates.append(x.rstrip('/'))
            except Exception as e:
                self.log('防封页获取失败 %s %s' % (u, e))
        candidates = list(dict.fromkeys(candidates + [self.fallback, self.publish, self.permanent]))
        for h in candidates:
            try:
                r = self.req(h.rstrip('/') + '/', timeout=10)
                txt = self.textOf(r)
                if getattr(r, 'status_code', 0) == 200 and ('百合TV' in txt or 'p?v=' in txt):
                    self.host = h.rstrip('/')
                    self.host_time = time.time()
                    self.headers['Referer'] = self.host + '/'
                    return self.host
            except Exception:
                pass
        self.host = self.fallback
        self.host_time = time.time()
        return self.host

    def refreshHome(self, force=False):
        if self.home_ready and not force:
            return
        try:
            host = self.getHost(force=force)
            top = []
            filters = {}
            for tid in ['1','2','3','4','5']:
                txt = self.htmlRaw(host + '/t?t_id=' + tid, timeout=12)
                vals, seen = [{'n':'全部','v':''}], set([''])
                for href, name in re.findall(r'href=["\']([^"\']*t\?t_id=%s[^"\']*)["\'][^>]*>([\s\S]{0,120}?)</a>' % tid, txt, re.I):
                    nm = self.clean(name)
                    m = re.search(r't_cid=(\d+)', href)
                    if m and nm and nm not in ['更多','上一页','下一页'] and m.group(1) not in seen:
                        seen.add(m.group(1)); vals.append({'n':nm,'v':m.group(1)})
                filters[tid] = [{'key':'cid','name':'分类','value':vals}]
                top.append({'type_id':tid,'type_name':'视频%s区' % '一二三四五'[int(tid)-1]})
            if top:
                self.classes = top
                self.filters = filters
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
        cid = (extend or {}).get('cid') or ''
        path = '/t?t_id=%s' % quote(str(tid or '1'))
        if cid: path += '&t_cid=' + quote(str(cid))
        if pg != '1': path += '&page=' + pg
        vods = self.parseList(self.host + path)
        return {'list':vods,'page':int(pg),'pagecount':999999 if vods else int(pg),'limit':16,'total':999999 if vods else 0}

    def detailContent(self, ids):
        self.getHost()
        raw = str(ids[0])
        ps = raw.split('|$|')
        vid = ps[0]
        old_name = unquote(ps[1]) if len(ps) > 1 else ''
        old_pic = ps[2] if len(ps) > 2 else ''
        url = self.fix(vid)
        name, pic, play = old_name, old_pic, ''
        try:
            txt = self.htmlRaw(url)
            name = self.clean(self.find1(txt, r'<title[^>]*>([\s\S]*?)(?:\s*-\s*bh44|\s*-\s*百合TV|</title>)')) or name
            pic = self.find1(txt, r'pic\s*:\s*["\']([^"\']+)["\']') or old_pic
            play = self.find1(txt, r'url\s*:\s*["\']([^"\']+?\.(?:m3u8|mp4)[^"\']*)["\']')
        except Exception as e:
            self.log('详情解析失败 %s' % e)
        play_url = '播放$' + (play or url)
        return {'list':[{'vod_id':url,'vod_name':name or '视频','vod_pic':self.fix(pic),'type_name':'','vod_year':'','vod_area':'','vod_remarks':'','vod_actor':'','vod_director':'','vod_content':name or '','vod_play_from':'高清','vod_play_url':play_url}]}

    def searchContent(self, key, quick, pg='1'):
        self.getHost()
        pg = str(pg or '1')
        path = '/s?k=' + quote(key or '')
        if pg != '1': path += '&page=' + pg
        vods = self.parseList(self.host + path)
        return {'list':vods,'page':int(pg),'total':len(vods)}

    def playerContent(self, flag, id, vipFlags):
        self.getHost()
        url = id
        try:
            if not self.isMedia(url):
                txt = self.htmlRaw(self.fix(url))
                url = self.find1(txt, r'url\s*:\s*["\']([^"\']+?\.(?:m3u8|mp4)[^"\']*)["\']') or self.find1(txt, r'(https?://[^"\'<>\s]+?\.(?:m3u8|mp4)[^"\'<>\s]*)') or url
        except Exception as e:
            self.log('播放解析失败 %s' % e)
        url = html.unescape((url or '').replace('\\/','/'))
        if self.isMedia(url):
            return {'parse':0,'url':url,'header':self.playHeader(url)}
        return {'parse':1,'url':id,'header':self.headers}

    def parseList(self, url):
        try:
            txt = self.htmlRaw(url)
            vods, seen = [], set()
            blocks = re.findall(r'(<a[^>]+href=["\']([^"\']*p\?v=\d+)["\'][\s\S]*?</a>)', txt, re.I)
            for block, href in blocks:
                href = self.fix(href)
                if href in seen: continue
                seen.add(href)
                name = self.clean(self.find1(block, r'<img[^>]+(?:title|alt)=["\']([^"\']+)') or self.find1(block, r'<p[^>]+class=["\'][^"\']*vod-name[^"\']*["\'][^>]*>([\s\S]*?)</p>'))
                pic = self.fix(self.find1(block, r'<img[^>]+src=["\']([^"\']+)'))
                remark = self.clean(self.find1(block, r'<p[^>]+class=["\'][^"\']*vod-class[^"\']*["\'][^>]*>([\s\S]*?)</p>') or self.find1(block, r'<span[^>]+class=["\'][^"\']*vod_hits[^"\']*["\'][^>]*>([\s\S]*?)</span>'))
                if not name: continue
                vods.append({'vod_id':href+'|$|'+quote(name)+'|$|'+pic,'vod_name':name,'vod_pic':pic,'vod_remarks':remark})
            return vods
        except Exception as e:
            self.log('列表解析失败 %s %s' % (url, e)); return []

    def req(self, url, timeout=15):
        # 真实 Jar 环境的 BaseSpider.fetch 可能强制校验证书，这里直连并关闭证书校验，避免防封域名 SSL 链不完整导致首页分类 null
        return requests.get(url, headers=self.headers, timeout=timeout, verify=False, allow_redirects=True)

    def htmlRaw(self, url, timeout=15):
        r = self.req(url, timeout=timeout)
        return self.textOf(r)
    def textOf(self, r):
        if hasattr(r, 'content'):
            return r.content.decode('utf-8','ignore')
        return getattr(r, 'text', '') or ''
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