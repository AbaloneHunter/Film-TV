# coding=utf-8
"""
目标站: 4K影视 (https://www.4kvm.me)
适配 TVBox Python 爬虫源 - 会话保持 + 强力解析
"""
import re
import sys
import json
import urllib.parse
import requests
from bs4 import BeautifulSoup

sys.path.append('..')
from base.spider import Spider


class Spider(Spider):

    def init(self, extend=""):
        self.site_url = "https://www.4kvm.me"
        if extend:
            try:
                ext_data = json.loads(ext)
                if 'host' in ext_data:
                    self.site_url = ext_data['host']
            except:
                pass
        # 创建 Session 保持 Cookie
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Referer': self.site_url,
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'same-origin',
            'Sec-Fetch-User': '?1',
            'Upgrade-Insecure-Requests': '1',
        })
        # 先访问首页获取 Cookie
        try:
            self.session.get(self.site_url, timeout=10)
        except:
            pass

    def fetch(self, url, headers=None):
        """重写 fetch 使用 session"""
        try:
            if headers:
                # 合并头部
                self.session.headers.update(headers)
            resp = self.session.get(url, timeout=15)
            return resp
        except Exception as e:
            print(f"请求失败: {e}")
            return None

    def homeContent(self, filter):
        categories = [
            {"type_id": "movie", "type_name": "电影"},
            {"type_id": "tv", "type_name": "电视剧"},
            {"type_id": "anime", "type_name": "动漫"},
        ]
        url = f"{self.site_url}/"
        resp = self.fetch(url)
        video_list = []
        if resp and resp.status_code == 200:
            soup = BeautifulSoup(resp.text, 'html.parser')
            cards = soup.select('.movie-card')[:20]
            for card in cards:
                item = self._parse_video_item(card)
                if item:
                    video_list.append(item)
        return {
            "class": categories,
            "list": video_list,
            "filters": {}
        }

    def homeVideoContent(self):
        return self.homeContent(False)

    def categoryContent(self, tid, pg, filter, extend):
        page = int(pg) if pg else 1
        # 尝试多个 URL 格式
        base_urls = [
            f"{self.site_url}/{tid}",
            f"{self.site_url}/{tid}/",
        ]
        # 若 page>1，添加分页参数
        if page > 1:
            params = {'page': page}
            if extend:
                for k, v in extend.items():
                    if v:
                        params[k] = v
            query = urllib.parse.urlencode(params)
            base_urls = [u + '?' + query for u in base_urls]
            # 也可能使用路径分页
            base_urls.append(f"{self.site_url}/{tid}/page/{page}/")
            base_urls.append(f"{self.site_url}/{tid}/{page}/")
        else:
            # 第一页也可能带参数
            if extend:
                params = {}
                for k, v in extend.items():
                    if v:
                        params[k] = v
                if params:
                    query = urllib.parse.urlencode(params)
                    base_urls = [u + '?' + query for u in base_urls]

        # 依次尝试，直到成功
        resp = None
        for url in base_urls:
            resp = self.fetch(url)
            if resp and resp.status_code == 200:
                break
        result = {"list": [], "page": page, "pagecount": 1, "limit": 24, "total": 0}
        if not resp or resp.status_code != 200:
            # 如果所有请求都失败，返回空
            return result

        soup = BeautifulSoup(resp.text, 'html.parser')
        video_list = []

        # 策略1：使用 .movie-card 选择器
        cards = soup.select('.movie-card')
        if cards:
            for card in cards:
                item = self._parse_video_item(card)
                if item:
                    video_list.append(item)

        # 策略2：若未获取到，尝试其他常见选择器
        if not video_list:
            other_selectors = ['.video-card', '.list-item', '.module-item', '.item', '.vod-item']
            for sel in other_selectors:
                cards = soup.select(sel)
                if cards:
                    for card in cards:
                        item = self._parse_video_item(card)
                        if item:
                            video_list.append(item)
                    break

        # 策略3：使用正则强力提取所有 /play/ 链接（终极后备）
        if not video_list:
            # 提取所有 /play/ 链接
            pattern = r'<a[^>]+href="(/play/[^"]+)"[^>]*>([^<]*)</a>'
            matches = re.findall(pattern, resp.text, re.IGNORECASE)
            seen = set()
            for href, name in matches:
                if href in seen:
                    continue
                seen.add(href)
                vid_match = re.search(r'/play/([^/?#]+)', href)
                if vid_match:
                    vod_id = vid_match.group(1)
                    # 尝试获取图片（从附近 img 的 data-src）
                    pic = ''
                    # 简单方式：查找第一个不包含 nopic 的 data-src
                    img_pattern = r'<img[^>]+data-src="([^"]+)"[^>]*>'
                    img_matches = re.findall(img_pattern, resp.text)
                    for im in img_matches:
                        if 'nopic' not in im.lower():
                            pic = im
                            break
                    # 如果名称是空，尝试从其他地方获取
                    if not name.strip():
                        # 尝试在链接附近找文本
                        name_pattern = r'<h3[^>]*>([^<]+)</h3>'
                        h3_matches = re.findall(name_pattern, resp.text)
                        if h3_matches:
                            name = h3_matches[0].strip()
                    video_list.append({
                        "vod_id": vod_id,
                        "vod_name": name.strip() or vod_id,
                        "vod_pic": pic,
                        "vod_remarks": ""
                    })
            # 去重（按 id）
            unique = {}
            for item in video_list:
                if item['vod_id'] not in unique:
                    unique[item['vod_id']] = item
            video_list = list(unique.values())

        result["list"] = video_list

        # 分页信息（简单处理）
        pagination = soup.select('.pagination a') or soup.select('.page a')
        pagecount = 1
        if pagination:
            for a in pagination:
                text = a.get_text(strip=True)
                if text.isdigit():
                    pagecount = max(pagecount, int(text))
            if pagecount == 1 and any('下一页' in a.get_text() for a in pagination):
                pagecount = page + 1
        result["pagecount"] = pagecount
        result["total"] = len(video_list) * pagecount
        return result

    def detailContent(self, ids):
        if not ids:
            return {"list": []}
        vid = ids[0]
        url = f"{self.site_url}/play/{vid}"
        resp = self.fetch(url)
        if not resp or resp.status_code != 200:
            return {"list": []}

        soup = BeautifulSoup(resp.text, 'html.parser')
        # 提取名称
        vod_name = ""
        name_tag = soup.select_one('h1') or soup.select_one('h2') or soup.select_one('.title')
        if name_tag:
            vod_name = name_tag.get_text(strip=True)
        if not vod_name:
            meta_title = soup.select_one('meta[property="og:title"]')
            if meta_title:
                vod_name = meta_title.get('content', '')

        # 提取海报
        vod_pic = ""
        img_tag = soup.select_one('.detail-info img') or soup.select_one('.vod-pic img') or soup.select_one('.pic img')
        if img_tag:
            vod_pic = img_tag.get('data-src', '') or img_tag.get('src', '')
            if 'nopic' in vod_pic.lower():
                vod_pic = ""

        # 提取导演、演员、简介
        director = actor = vod_content = ""
        info_blocks = soup.select('.detail-info') or soup.select('.vod-detail') or soup.select('.info')
        if info_blocks:
            for block in info_blocks:
                texts = block.get_text(separator='\n', strip=True).split('\n')
                for line in texts:
                    line = line.strip()
                    if '导演' in line:
                        director = line.replace('导演', '').replace('：', '').strip()
                    elif '主演' in line or '演员' in line:
                        actor = line.replace('主演', '').replace('演员', '').replace('：', '').strip()
                    elif '简介' in line or '剧情' in line:
                        vod_content = line.replace('简介', '').replace('剧情', '').replace('：', '').strip()
            if not vod_content:
                desc = soup.select_one('.desc') or soup.select_one('.content')
                if desc:
                    vod_content = desc.get_text(strip=True)

        # 播放列表
        play_from_list = []
        play_url_list = []
        source_containers = soup.select('.play-source') or soup.select('.playlist') or soup.select('.episode-list')
        if not source_containers:
            all_episodes = soup.select('a[href*="/play/"]')
            if all_episodes:
                episodes = []
                for a in all_episodes:
                    ep_name = a.get_text(strip=True)
                    ep_link = a.get('href', '')
                    if ep_link and 'javascript' not in ep_link:
                        if not ep_link.startswith('http'):
                            ep_link = urllib.parse.urljoin(self.site_url, ep_link)
                        episodes.append(f"{ep_name}${ep_link}")
                if episodes:
                    play_from_list.append("默认线路")
                    play_url_list.append('#'.join(episodes))
        else:
            for container in source_containers:
                source_name = container.select_one('.play-source-name') or container.select_one('.source-name')
                if source_name:
                    source_name = source_name.get_text(strip=True)
                else:
                    source_name = "未知线路"
                episodes = []
                ep_items = container.select('.play-item') or container.select('.episode-item') or container.select('a')
                for a in ep_items:
                    ep_name = a.get_text(strip=True)
                    ep_link = a.get('href', '')
                    if ep_link and 'javascript' not in ep_link:
                        if not ep_link.startswith('http'):
                            ep_link = urllib.parse.urljoin(self.site_url, ep_link)
                        episodes.append(f"{ep_name}${ep_link}")
                if episodes:
                    play_from_list.append(source_name)
                    play_url_list.append('#'.join(episodes))

        if not play_from_list:
            all_links = soup.select('a[href*="/play/"]')
            if all_links:
                episodes = []
                for a in all_links:
                    ep_name = a.get_text(strip=True)
                    ep_link = a.get('href', '')
                    if ep_link and 'javascript' not in ep_link:
                        if not ep_link.startswith('http'):
                            ep_link = urllib.parse.urljoin(self.site_url, ep_link)
                        episodes.append(f"{ep_name}${ep_link}")
                if episodes:
                    play_from_list.append("默认线路")
                    play_url_list.append('#'.join(episodes))

        vod_play_from = '$$$'.join(play_from_list) if play_from_list else "默认线路"
        vod_play_url = '$$$'.join(play_url_list) if play_url_list else ""

        result = [{
            "vod_id": vid,
            "vod_name": vod_name,
            "vod_pic": vod_pic,
            "vod_content": f"导演：{director}\n主演：{actor}\n简介：{vod_content}".strip(),
            "vod_play_from": vod_play_from,
            "vod_play_url": vod_play_url
        }]
        return {"list": result}

    def searchContent(self, key, quick, pg="1"):
        page = int(pg) if pg else 1
        url = f"{self.site_url}/search?q={urllib.parse.quote(key)}"
        if page > 1:
            url += f"&page={page}"
        resp = self.fetch(url)
        result = {"list": [], "page": page, "pagecount": 1}
        if not resp or resp.status_code != 200:
            return result
        soup = BeautifulSoup(resp.text, 'html.parser')
        cards = soup.select('.movie-card')
        for card in cards:
            item = self._parse_video_item(card)
            if item:
                result["list"].append(item)
        # 分页
        pagination = soup.select('.pagination a') or soup.select('.page a')
        pagecount = 1
        for a in pagination:
            text = a.get_text(strip=True)
            if text.isdigit():
                pagecount = max(pagecount, int(text))
        if pagecount == 1 and any('下一页' in a.get_text() for a in pagination):
            pagecount = page + 1
        result["pagecount"] = pagecount
        return result

    def playerContent(self, flag, id, vipFlags):
        if not id.startswith('http'):
            url = urllib.parse.urljoin(self.site_url, id)
        else:
            url = id
        return {"parse": 1, "url": url, "header": self.session.headers}

    # ---------- 辅助方法 ----------
    def _parse_video_item(self, card):
        vod_id = card.get('data-vod-id', '')
        if not vod_id:
            link_tag = card.select_one('a')
            if link_tag:
                href = link_tag.get('href', '')
                match = re.search(r'/play/([^/?#]+)', href)
                if match:
                    vod_id = match.group(1)
        if not vod_id:
            return None

        title_tag = card.select_one('h3')
        vod_name = title_tag.get_text(strip=True) if title_tag else ''

        img_tag = card.select_one('img')
        vod_pic = ''
        if img_tag:
            vod_pic = img_tag.get('data-src', '') or img_tag.get('src', '')
            if 'nopic' in vod_pic.lower():
                vod_pic = ''

        vod_remarks = ''
        spans = card.select('span')
        for span in spans:
            text = span.get_text(strip=True)
            if any(kw in text for kw in ['更新至', '全', '集', 'HD']):
                if '4k' in text and len(text) < 10:
                    continue
                vod_remarks = text
                break
        if not vod_remarks:
            last_span = card.select_one('span:last-child')
            if last_span:
                vod_remarks = last_span.get_text(strip=True)

        if not vod_name:
            return None
        return {
            "vod_id": vod_id,
            "vod_name": vod_name,
            "vod_pic": vod_pic,
            "vod_remarks": vod_remarks
        }