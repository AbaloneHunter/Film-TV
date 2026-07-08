# -*- coding: utf-8 -*-
import hashlib
import base64
import json
import time
import requests
from base.spider import Spider

class Spider(Spider):
    # 接口地址
    h_ost = 'https://api-store.qmplaylet.com'
    h_ost1 = 'https://api-read.qmplaylet.com'
    
    # 签名密钥 (从JS代码提取)
    keys = 'd3dGiJc651gSQ8w1'
    
    # 字符映射表 (从JS代码完整移植)
    char_map = {
        '+': 'P', '/': 'X', '0': 'M', '1': 'U', '2': 'l', '3': 'E', '4': 'r', '5': 'Y', 
        '6': 'W', '7': 'b', '8': 'd', '9': 'J', 'A': '9', 'B': 's', 'C': 'a', 'D': 'I', 
        'E': '0', 'F': 'o', 'G': 'y', 'H': '_', 'I': 'H', 'J': 'G', 'K': 'i', 'L': 't', 
        'M': 'g', 'N': 'N', 'O': 'A', 'P': '8', 'Q': 'F', 'R': 'k', 'S': '3', 'T': 'h', 
        'U': 'f', 'V': 'R', 'W': 'q', 'X': 'C', 'Y': '4', 'Z': 'p', 'a': 'm', 'b': 'B', 
        'c': 'O', 'd': 'u', 'e': 'c', 'f': '6', 'g': 'K', 'h': 'x', 'i': '5', 'j': 'T', 
        'k': '-', 'l': '2', 'm': 'z', 'n': 'S', 'o': 'Z', 'p': '1', 'q': 'V', 'r': 'v', 
        's': 'j', 't': 'Q', 'u': '7', 'v': 'D', 'w': 'w', 'x': 'n', 'y': 'L', 'z': 'e'
    }

    def init(self, extend):
        print("[七猫短剧] Python版初始化完成")

    def getName(self):
        return "七猫短剧"

    # ================= 核心算法移植 =================

    def _md5(self, text):
        """MD5签名"""
        return hashlib.md5(text.encode('utf-8')).hexdigest()

    def _get_qm_params_and_sign(self):
        """
        生成请求所需的 qm-params 和 sign
        移植自 JS 的 getQmParamsAndSign 函数
        """
        session_id = str(int(time.time() * 1000))
        
        data = {
            "static_score": "0.8", "uuid": "00000000-7fc7-08dc-0000-000000000000",
            "device-id": "20250220125449b9b8cac84c2dd3d035c9052a2572f7dd0122edde3cc42a70",
            "mac": "", "sourceuid": "aa7de295aad621a6", "refresh-type": "0", "model": "22021211RC",
            "wlb-imei": "", "client-id": "aa7de295aad621a6", "brand": "Redmi", "oaid": "",
            "oaid-no-cache": "", "sys-ver": "12", "trusted-id": "", "phone-level": "H",
            "imei": "", "wlb-uid": "aa7de295aad621a6", "session-id": session_id
        }
        
        # 1. 序列化并Base64编码
        json_str = json.dumps(data, separators=(',', ':'))
        base64_str = base64.b64encode(json_str.encode('utf-8')).decode('utf-8')
        
        # 2. 字符映射 (核心加密逻辑)
        qm_params = ''.join([self.char_map.get(c, c) for c in base64_str])
        
        # 3. 生成签名串
        params_str = f"AUTHORIZATION=app-version=10001application-id=com.duoduo.readchannel=unknownis-white=net-env=5platform=androidqm-params={qm_params}reg={self.keys}"
        sign = self._md5(params_str)
        
        return qm_params, sign

    def _get_headers(self):
        """构造请求头"""
        qm_params, sign = self._get_qm_params_and_sign()
        return {
            'net-env': '5', 'reg': '', 'channel': 'unknown', 'is-white': '', 'platform': 'android',
            'application-id': 'com.duoduo.read', 'authorization': '', 'app-version': '10001',
            'user-agent': 'webviewversion/0', 'qm-params': qm_params,
            'sign': sign
        }

    # ================= 爬虫标准接口 =================

    def homeContent(self, filter):
        """首页分类"""
        # 构造签名
        sign_str = f"operation=1playlet_privacy=1tag_id=0{self.keys}"
        api_sign = self._md5(sign_str)
        url = f"{self.h_ost}/api/v1/playlet/index?tag_id=0&playlet_privacy=1&operation=1&sign={api_sign}"
        
        try:
            headers = self._get_headers()
            res = requests.get(url, headers=headers, timeout=10)
            data = res.json()
            
            classes = []
            # JS逻辑：遍历前5个分类组
            for i in range(5):
                tags = data.get('data', {}).get('tag_categories', [])[i].get('tags', [])
                for tag in tags:
                    classes.append({
                        'type_id': str(tag.get('tag_id')),
                        'type_name': tag.get('tag_name', '')
                    })
            
            return {'class': classes}
        except Exception as e:
            print(f"[七猫] 首页请求失败: {e}")
            return {'class': []}

    def categoryContent(self, tid, pg, filter, extend):
        """分类页"""
        page = pg if pg else 1
        
        # JS逻辑：第一页和翻页的签名串不同
        if page == 1:
            sign_str = f"operation=1playlet_privacy=1tag_id={tid}{self.keys}"
            url = f"{self.h_ost}/api/v1/playlet/index?tag_id={tid}&playlet_privacy=1&operation=1&sign={self._md5(sign_str)}"
        else:
            sign_str = f"next_id={page}operation=1playlet_privacy=1tag_id={tid}{self.keys}"
            url = f"{self.h_ost}/api/v1/playlet/index?tag_id={tid}&next_id={page}&playlet_privacy=1&operation=1&sign={self._md5(sign_str)}"
        
        try:
            headers = self._get_headers()
            res = requests.get(url, headers=headers, timeout=10)
            data = res.json()
            
            videos = []
            video_list = data.get('data', {}).get('list', [])
            
            for vod in video_list:
                videos.append({
                    'vod_id': str(vod.get('playlet_id')),
                    'vod_name': vod.get('title', '未知标题'),
                    'vod_pic': vod.get('image_link', ''),
                    'vod_remarks': f"{vod.get('total_episode_num')}集 · {vod.get('hot_value')}"
                })
            
            return {
                'page': int(page),
                'list': videos
            }
        except Exception as e:
            print(f"[七猫] 分类请求失败: {e}")
            return {'list': []}

    def detailContent(self, array):
        """详情页"""
        if not array: return {}
        vid = array[0]
        
        # 构造签名
        sign_str = f"playlet_id={vid}{self.keys}"
        sign = self._md5(sign_str)
        url = f"{self.h_ost1}/player/api/v1/playlet/info?playlet_id={vid}&sign={sign}"
        
        try:
            headers = self._get_headers()
            res = requests.get(url, headers=headers, timeout=10)
            data = res.json().get('data', {})
            
            # 构造播放列表
            play_list = data.get('play_list', [])
            play_url = '#'.join([f"{item.get('sort')}${item.get('video_url')}" for item in play_list])
            
            vod = {
                'vod_id': vid,
                'vod_name': data.get('title', '未知标题'),
                'vod_pic': data.get('image_link', ''),
                'vod_remarks': f"{data.get('total_episode_num')}集",
                'vod_content': data.get('intro', ''),
                'vod_play_from': '七猫短剧',
                'vod_play_url': play_url
            }
            
            return {'list': [vod]}
        except Exception as e:
            print(f"[七猫] 详情请求失败: {e}")
            return {'list': []}

    def searchContent(self, key, quick, pg="1"):
        """搜索"""
        page = pg if pg else 1
        track_id = 'ec1280db127955061754851657967'
        
        # 构造签名
        # JS逻辑：参数需拼接
        sign_str = f"extend=page={page}read_preference=0track_id={track_id}wd={key}{self.keys}"
        sign = self._md5(sign_str)
        
        url = f"{self.h_ost}/api/v1/playlet/search?extend=&page={page}&wd={key}&read_preference=0&track_id={track_id}&sign={sign}"
        
        try:
            headers = self._get_headers()
            res = requests.get(url, headers=headers, timeout=10)
            data = res.json()
            
            videos = []
            video_list = data.get('data', {}).get('list', [])
            
            for vod in video_list:
                # 去除HTML标签
                name = vod.get('title', '').replace('<[^>]+>', '')
                videos.append({
                    'vod_id': str(vod.get('id')),
                    'vod_name': name,
                    'vod_pic': vod.get('image_link', ''),
                    'vod_remarks': f"{vod.get('total_num')}集"
                })
            
            return {'list': videos}
        except Exception as e:
            print(f"[七猫] 搜索请求失败: {e}")
            return {'list': []}

    def playerContent(self, flag, id, vipFlags):
        """播放"""
        # 直接返回URL，无需二次解密
        return {
            'parse': 0,
            'url': id
        }

    def localProxy(self, param):
        return {}
