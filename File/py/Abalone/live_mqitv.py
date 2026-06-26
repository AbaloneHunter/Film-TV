import json
import re
import sys
import time
import requests
import base64
from urllib.parse import urlparse, urlencode
sys.path.append('..')
from base.spider import Spider

class Spider(Spider):
    def getName(self):
        return "MQ酒店源"
    
    def init(self, extend):
        try:
            self.extendDict = json.loads(extend) if extend else {}
            self.proxy = self.extendDict.get('proxy')
            self.is_proxy = self.proxy is not None
            self.SERVERS = [
                "https://59.125.210.231:4433",
                "https://60.248.127.232:4433"
            ]
            self.USER_PATTERN = re.compile(r'.*?([0-9a-zA-Z]{11,}).*', re.IGNORECASE)
            self.MAC_PATTERN = re.compile(r'.*?(([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}).*', re.IGNORECASE)
            self.m3u8_headers = {'User-Agent': 'okhttp/5.0.0-alpha.14','Accept': '*/*','Connection': 'keep-alive','Accept-Encoding': 'gzip','CLIENT-IP': '127.0.0.1','X-FORWARDED-FOR': '127.0.0.1'}
            self.ts_headers = {'User-Agent': 'com.fongmi.android.tv/4.0.4 (Linux;Android 14) AndroidXMedia3/1.6.1','Accept': '*/*','Connection': 'keep-alive','Accept-Encoding': 'identity','CLIENT-IP': '127.0.0.1','X-FORWARDED-FOR': '127.0.0.1'}
            self.current_token = None
            self.current_server_url = None
            self.token_expiry = 0
            self.valid_users = []
            self.channel_data = []
            self.session = requests.Session()
            self.session.verify = False
            self.session.headers.update(self.m3u8_headers)
        except Exception as e:
            pass
    
    def getDependence(self):
        return []
    
    def isVideoFormat(self, url):
        return False
    
    def manualVideoCheck(self):
        return False
    
    def liveContent(self, url):
        if not self._ensure_valid_token():
            return "#EXTM3U\n"
        tv_list = ['#EXTM3U']
        parsed_server = urlparse(self.current_server_url)
        server_host = parsed_server.hostname
        for channel in self.channel_data:
            cid = channel.get('id', '')
            if not cid:
                continue
            name = channel.get('name', cid)
            tvg_id = channel.get('tvg-id', cid)
            tvg_name = channel.get('tvg-name', name)
            tvg_logo = channel.get('logo', '')
            group_title = channel.get('group', '酒店频道')
            port = channel.get('port', '80')
            tv_list.append(
                f'#EXTINF:-1 tvg-id="{tvg_id}" tvg-name="{tvg_name}" tvg-logo="{tvg_logo}" group-title="{group_title}",{name}'
            )
            tv_list.append(f'{self.getProxyUrl()}&fun=mq&cid={cid}&server={server_host}&port={port}&token={self.current_token}')
        return '\n'.join(tv_list)
    
    def _ensure_valid_token(self):
        if self.current_token and time.time() < self.token_expiry:
            return True
        if self.valid_users:
            for user in self.valid_users:
                token = self._get_user_token(user['server_url'], user['user_id'], user['mac'])
                if token:
                    self.current_token = token
                    self.current_server_url = user['server_url']
                    self.token_expiry = time.time() + 3600
                    return True
        for server_url in self.SERVERS:
            try:
                self.channel_data = self._get_server_channels(server_url)
                if not self.channel_data:
                    continue
                users = self._extract_users(server_url, self.channel_data)
                if users:
                    self.valid_users = users
                    user = users[0]
                    self.current_token = user['token']
                    self.current_server_url = server_url
                    self.token_expiry = time.time() + 3600
                    return True
            except Exception as e:
                pass
        return False
    
    def _get_server_channels(self, server_url):
        api_url = f"{server_url}/api/post?item=itv_traffic"
        try:
            response = self.session.get(
                api_url,
                timeout=15,
                proxies={"http": self.proxy, "https": self.proxy} if self.is_proxy else None
            )
            if response.status_code != 200:
                return []
            json_data = response.json()
            data_list = json_data.get('data', [])
            if not data_list:
                return []
            channels = []
            for item in data_list:
                if not isinstance(item, dict):
                    continue
                stat_obj = item.get('stat', {})
                user_ip_list = stat_obj.get('UserIpList', []) if isinstance(stat_obj, dict) else []
                if not isinstance(user_ip_list, list):
                    user_ip_list = []
                channels.append({
                    'id': item.get('id', ''),
                    'name': item.get('name', ''),
                    'logo': item.get('logo', ''),
                    'group': item.get('group', '酒店频道'),
                    'port': str(item.get('port', '80')),
                    'tvg-id': item.get('id', ''),
                    'tvg-name': item.get('name', ''),
                    'user_ip_list': user_ip_list
                })
            return channels
        except Exception as e:
            return []
    
    def _extract_users(self, server_url, channels):
        users = []
        for channel in channels:
            if len(users) >= 3:
                break
            for user_ip in channel.get('user_ip_list', []):
                user_str = str(user_ip)
                user_match = self.USER_PATTERN.match(user_str)
                mac_match = self.MAC_PATTERN.match(user_str)
                if user_match and mac_match:
                    user_id = user_match.group(1)
                    mac = mac_match.group(1)
                    token = self._get_user_token(server_url, user_id, mac)
                    if token:
                        users.append({
                            'server_url': server_url,
                            'user_id': user_id,
                            'mac': mac,
                            'token': token
                        })
        return users
    
    def _get_user_token(self, server_url, user_id, mac):
        timestamp = int(time.time() * 1000)
        token_url = f"{server_url}/HSAndroidLogin.ecgi?ty=json&net_account={user_id}&mac_address1={mac}&_={timestamp}"
        try:
            response = self.session.get(
                token_url,
                timeout=15,
                proxies={"http": self.proxy, "https": self.proxy} if self.is_proxy else None
            )
            if response.status_code != 200:
                return None
            match = re.search(r'"Token"\s*:\s*"(.*?)"', response.text, re.IGNORECASE)
            if match:
                return match.group(1)
            return None
        except Exception as e:
            return None
    
    def homeContent(self, filter):
        return {}
    
    def homeVideoContent(self):
        return {}
    
    def categoryContent(self, cid, page, filter, ext):
        return {}
    
    def detailContent(self, did):
        return {}
    
    def searchContent(self, key, quick, page='1'):
        return {}
    
    def searchContentPage(self, keywords, quick, page):
        return {}
    
    def playerContent(self, flag, cid, vipFlags):
        return {}
    
    def localProxy(self, params):
        _fun = params.get('fun', None)
        _type = params.get('type', None)
        if _fun is not None:
            fun = getattr(self, f'fun_{_fun}')
            return fun(params)
        if _type is not None:
            if params['type'] == "m3u8":
                return self.get_m3u8_text(params)
            if params['type'] == "ts":
                return self.get_ts(params)
        return [302, "text/plain", None, {'Location': 'https://sf1-cdn-tos.huoshanstatic.com/obj/media-fe/xgplayer_doc_video/mp4/xgplayer-demo-720p.mp4'}]
    
    def fun_mq(self, params):
        cid = params['cid']
        server_host = params['server']
        port = params['port']
        token = params['token']
        url = f"http://{server_host}:{port}/{cid}.m3u8?token={token}"
        play_url = self.b64encode(url)
        url = f"{self.getProxyUrl()}&type=m3u8&url={play_url}"
        return [302, "text/plain", None, {'Location': url}]
    
    def get_m3u8_text(self, params):
        url = self.b64decode(params['url'])
        headers = self.m3u8_headers
        home_url = url.replace(url.split('/')[-1], '')
        def callback_function(match):
            uri = home_url + match.group(1)
            a = self.b64encode(uri)
            return f"{self.getProxyUrl()}&type=ts&url={a}"
        if self.is_proxy:
            response = requests.get(url, headers=headers, proxies=self.proxy)
        else:
            response = requests.get(url, headers=headers)
        m3u8_text = re.sub(r'(.*\.ts.*)', callback_function, response.text)
        return [200, "application/vnd.apple.mpegurl", m3u8_text]      
    
    def get_ts(self, params):
        url = self.b64decode(params['url'])
        headers = self.ts_headers
        if self.is_proxy:
            response = requests.get(url, headers=headers, proxies=self.proxy)
        else:
            response = requests.get(url, headers=headers)
        return [206, "application/octet-stream", response.content]        
    
    def destroy(self):
        return "已销毁"
    
    def b64encode(self, data):
        return base64.b64encode(data.encode('utf-8')).decode('utf-8')
    
    def b64decode(self, data):
        try:
            return base64.b64decode(data.encode('utf-8')).decode('utf-8')
        except:
            return ""

if __name__ == '__main__':
    pass