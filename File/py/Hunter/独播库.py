# -*- coding: utf-8 -*-
import base64
import time
import random
import re
import json
import requests
from base.spider import Spider

class Spider(Spider):
    host = 'https://api.dbokutv.com'
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Referer": "https://www.duboku.tv/"
    }

    def init(self, extend):
        print("[独播库] Python版初始化完成")
        pass

    def getName(self):
        return "独播库"

    # ================= 核心算法：解码与签名 (从JS移植) =================
    
    def _base64_decode(self, str_data):
        """标准的Base64解码"""
        # JS代码中处理了.=的情况，Python直接处理
        str_data = str_data.replace('.', '=')
        # 补齐padding
        missing_padding = len(str_data) % 4
        if missing_padding:
            str_data += '=' * (4 - missing_padding)
        try:
            return base64.b64decode(str_data).decode('utf-8')
        except:
            return ''

    def _decode_data(self, data):
        """
        移植自 JS 的 decodeData 函数
        逻辑：去除引号 -> 每10个字符反转 -> Base64解码
        """
        if not data: return ''
        
        # 1. 去除引号和空格
        clean_data = data.replace('"', '').replace("'", '').strip()
        if not clean_data: return ''
        
        # 2. 每10个字符为一组进行反转
        segment_len = 10
        processed_parts = []
        for i in range(0, len(clean_data), segment_len):
            segment = clean_data[i : i + segment_len]
            # 反转字符串
            processed_parts.append(segment[::-1])
        
        # 3. 拼接并进行Base64解码
        b64_str = "".join(processed_parts)
        return self._base64_decode(b64_str)

    def _random_str(self, length):
        """生成随机字符串"""
        chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        return ''.join(random.choice(chars) for _ in range(length))

    def _get_signed_url(self, path):
        """
        移植自 JS 的 getSignedUrl 函数
        生成带签名的请求URL
        """
        # 1. 时间戳
        timestamp = str(int(time.time()))
        
        # 2. 随机数逻辑
        rand_num = random.randint(0, 800000000)
        val_a = str(rand_num + 100000000)
        val_b = str(900000000 - rand_num)
        combined = val_a + val_b
        
        # 3. 交错拼接字符串
        interleaved = ''
        min_len = min(len(combined), len(timestamp))
        for i in range(min_len):
            interleaved += combined[i] + timestamp[i]
        
        # 补齐剩余部分
        interleaved += combined[min_len:] + timestamp[min_len:]
        
        # 4. Base64编码生成 ssid
        # 注意：JS里的base64Encode是标准编码，只是把=换成了.
        ssid_raw = base64.b64encode(interleaved.encode()).decode()
        ssid = ssid_raw.replace('=', '.')
        
        # 5. 生成随机 sign 和 token
        sign = self._random_str(60)
        token = self._random_str(38)
        
        # 6. 组装URL
        connector = '&' if '?' in path else '?'
        return f"{self.host}{path}{connector}sign={sign}&token={token}&ssid={ssid}"

    # ================= 爬虫标准接口 =================

    def homeContent(self, filter):
        """首页分类"""
        classes = [
            {'type_id': '2', 'type_name': '连续剧'},
            {'type_id': '1', 'type_name': '电影'},
            {'type_id': '3', 'type_name': '综艺'},
            {'type_id': '4', 'type_name': '动漫'}
        ]
        return {'class': classes}

    def categoryContent(self, tid, pg, filter, extend):
        """分类页"""
        page = pg if pg else 1
        # JS逻辑：第一页路径不带页码，其他页带页码
        page_str = '' if str(page) == '1' else str(page)
        
        # 构造路径: /vodshow/{tid}--------{page}---
        url_path = f"/vodshow/{tid}--------{page_str}---"
        full_url = self._get_signed_url(url_path)
        
        try:
            r = requests.get(full_url, headers=self.headers, timeout=10)
            json_data = r.json()
            
            videos = []
            # 解析视频列表
            for item in (json_data.get('VodList') or []):
                vid_raw = item.get('DId') or item.get('DuId')
                videos.append({
                    'vod_id': self._decode_data(vid_raw),
                    'vod_name': item.get('Name'),
                    'vod_pic': self._decode_data(item.get('TnId')),
                    'vod_remarks': item.get('Tag')
                })
            
            # 解析分页总数 (简单处理，默认返回当前页)
            # JS代码里有复杂的PaginationList解析，这里简化处理或可按需补充
            
            return {
                'page': int(page),
                'pagecount': int(page) + 1, # 简单假设还有下一页
                'list': videos
            }
        except Exception as e:
            print(f"[独播库] 分类请求失败: {e}")
            return {'list': []}

    def detailContent(self, array):
        """详情页"""
        if not array: return {}
        vid = array[0]
        
        # 注意：JS代码里 detail 传入的是 decode 后的 ID
        # 但通常列表页返回的 vod_id 已经是 decode 后的了
        # 假设 vid 已经是正确的 ID (如 '/voddetail/xxx')
        # 如果 vid 只是一个 hash，我们需要构造路径
        
        # JS代码逻辑: getSignedUrl(id) -> id 直接作为 path
        # 说明传入的 id 应该是类似 "/voddetail/..." 的路径
        # 如果列表页返回的 id 只是 hash，这里需要补全路径
        
        # 假设列表页返回的 id 是纯 ID，我们需要构造路径:
        if not vid.startswith('/'):
            detail_path = f"/voddetail/{vid}"
        else:
            detail_path = vid
            
        full_url = self._get_signed_url(detail_path)
        
        try:
            r = requests.get(full_url, headers=self.headers, timeout=10)
            data = r.json()
            
            # 解析播放列表
            play_parts = []
            for ep in (data.get('Playlist') or []):
                name = ep.get('EpisodeName')
                url_hash = ep.get('VId')
                play_parts.append(f"{name}${self._decode_data(url_hash)}")
            
            play_url = "#".join(play_parts)
            
            # 构造详情对象
            vod = {
                'vod_id': vid,
                'vod_name': data.get('Name'),
                'vod_pic': self._decode_data(data.get('TnId')),
                'vod_remarks': f"评分：{data.get('Rating')}",
                'vod_year': data.get('ReleaseYear'),
                'vod_area': data.get('Region'),
                'vod_actor': ','.join(data.get('Actor')) if isinstance(data.get('Actor'), list) else data.get('Actor'),
                'vod_director': data.get('Director'),
                'vod_content': data.get('Description'),
                'vod_play_from': '独播库',
                'vod_play_url': play_url
            }
            
            return {'list': [vod]}
        except Exception as e:
            print(f"[独播库] 详情请求失败: {e}")
            return {'list': []}

    def playerContent(self, flag, id, vipFlags):
        """播放页"""
        # id 是 decode 后的播放链接 hash
        # 需要请求接口获取真实播放地址
        
        # 构造路径: id 可能是 hash，需要补全
        if not id.startswith('/'):
            play_path = f"/vodplay/{id}"
        else:
            play_path = id
            
        full_url = self._get_signed_url(play_path)
        
        try:
            r = requests.get(full_url, headers=self.headers, timeout=10)
            res = r.json()
            
            real_url = self._decode_data(res.get('HId'))
            
            return {
                'parse': 0, # 0表示直接播放
                'url': real_url,
                'header': {
                    'User-Agent': self.headers['User-Agent'],
                    'Origin': 'https://w.duboku.io',
                    'Referer': 'https://w.duboku.io/'
                }
            }
        except Exception as e:
            return {'parse': 0, 'url': '', 'msg': str(e)}

    def searchContent(self, key, quick, pg="1"):
        """搜索"""
        # 搜索路径: /vodsearch?wd=xxx
        url_path = "/vodsearch"
        full_url = self._get_signed_url(url_path) + f"&wd={key}"
        
        try:
            r = requests.get(full_url, headers=self.headers, timeout=10)
            json_data = r.json()
            
            videos = []
            for item in (json_data or []):
                vid_raw = item.get('DId') or item.get('DuId')
                videos.append({
                    'vod_id': self._decode_data(vid_raw),
                    'vod_name': item.get('Name'),
                    'vod_pic': self._decode_data(item.get('TnId')),
                    'vod_remarks': item.get('Tag')
                })
            
            return {'list': videos}
        except:
            return {'list': []}

    def localProxy(self, param):
        return {}
