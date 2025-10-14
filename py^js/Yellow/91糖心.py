#!/data/data/com.termux/files/usr/bin/python
# 增强版视频采集工具 - 确保完整提取所有视频

import os  # 添加os模块用于系统命令

# 自动检查和安装依赖
try:
    import requests
except ImportError:
    print("requests 库未安装，正在自动安装...")
    os.system('pip install requests')
    import requests  # 安装后重新导入

import re
import time
import threading
import queue
import random
from urllib.parse import urljoin, quote
from datetime import datetime

# ============ 配置区 =============
cfg = {
    "site": "http://www.9191md.me/",
    "api": "http://www.9191md.me/index.php/vod/type/id/{tid}/page/{pg}.html",
    "types": "1&2&3&4&5&6&7&8&9&10&20&21&22&23&24&25&26&27&28&29&30",
    "names": "麻豆&制片厂&天美&蜜桃&皇家&星空&精东&乐播&头条&乌鸦&兔子&杏吧&玩偶&mini&大象&开心鬼&萝莉&Psycho&性世界&糖心&性视界",
    "threads": 15,  # 减少线程数以避免被封
    "timeout": 15,
    "retry": 3,    # 重试次数
    "delay": 1.5   # 请求延迟
}
# ============ 配置结束 =============

class EnhancedVideoFetcher:
    def __init__(self):
        self.results = {}
        self.lock = threading.Lock()
        self.stop_flag = False
        self.stats = {'videos': 0, 'pages': 0, 'categories': 0, 'retries': 0}
        
        # 初始化结果字典
        for name in cfg["names"].split('&'):
            self.results[name] = []
    
    def fetch_with_retry(self, url, retries=cfg["retry"]):
        """带重试机制的网页内容获取"""
        for attempt in range(retries):
            try:
                response = requests.get(url, headers={
                    'User-Agent': 'Mozilla/5.0 (Linux; Android 10; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36',
                    'Referer': cfg["site"],
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language': 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
                    'Accept-Encoding': 'gzip, deflate',
                    'Connection': 'keep-alive',
                    'Upgrade-Insecure-Requests': '1'
                }, timeout=cfg["timeout"])
                
                response.encoding = 'utf-8'
                return response.text
                
            except Exception as e:
                if attempt < retries - 1:
                    time.sleep(2 ** attempt)  # 指数退避
                    with self.lock:
                        self.stats['retries'] += 1
                else:
                    print(f"获取失败: {url}, 错误: {e}")
                    return ""
    
    def get_total_pages(self, html):
        """获取总页数"""
        patterns = [
            r'page.*?/(\d+).html["\']\s*>[末页]',
            r'共(\d+)页',
            r'总页数.*?(\d+)',
            r'pages.*?(\d+)',
            r'/(\d+).html["\']\s*class=["\']last["\']'
        ]
        
        for pattern in patterns:
            match = re.search(pattern, html, re.I)
            if match:
                return min(int(match.group(1)), 100)  # 限制最大页数
        
        # 如果没有找到分页信息，尝试查找是否有下一页按钮
        if '下一页' in html or 'next' in html.lower():
            return 50  # 默认最大页数
        
        return 1  # 默认只有一页
    
    def extract_m3u8_urls(self, html):
        """从HTML中提取所有可能的m3u8链接"""
        urls = []
        
        # 方法1: 从JavaScript中提取
        js_patterns = [
            r'"url"\s*:\s*"([^"]+\.m3u8[^"]*)"',
            r'player.*?\.src\(["\']([^"\']+\.m3u8[^"\']*)["\']\)',
            r'url\s*=\s*["\']([^"\']+\.m3u8[^"\']*)["\']'
        ]
        
        for pattern in js_patterns:
            matches = re.findall(pattern, html, re.I)
            urls.extend(matches)
        
        # 方法2: 直接查找m3u8链接
        direct_patterns = [
            r'["\'](https?://[^"\']+\.m3u8[^"\']*)["\']',
            r'(https?://[^"\']+\.m3u8)'
        ]
        
        for pattern in direct_patterns:
            matches = re.findall(pattern, html, re.I)
            urls.extend(matches)
        
        # 清理URL
        cleaned_urls = []
        for url in urls:
            url = url.replace('\\/', '/').replace('\\"', '"')
            if 'm3u8' in url and not any(x in url for x in ['javascript:', 'void(0)']):
                cleaned_urls.append(url)
        
        return cleaned_urls
    
    def get_best_m3u8_url(self, urls):
        """从多个URL中选择最佳的m3u8链接"""
        if not urls:
            return None
        
        # 优先选择CDN链接
        for url in urls:
            if any(cdn in url for cdn in ['cdn', 't26', 't25', 't24', 't23']):
                return url
        
        # 如果没有CDN链接，返回第一个
        return urls[0]
    
    def get_clean_title(self, title):
        """清理标题，只保留中文和点号"""
        # 提取中文和点号
        chinese_parts = re.findall(r'[^\x00-\x7F]+|[\.]', title)
        clean_title = ''.join(chinese_parts)
        
        # 去除开头的非中文字符
        clean_title = re.sub(r'^[^\u4e00-\u9fff]*', '', clean_title)
        
        return clean_title
    
    def extract_video_blocks(self, html):
        """从HTML中提取视频块"""
        # 多种可能的视频块模式
        patterns = [
            r'<div class="video-item">(.*?)</div>',
            r'<li class="video-item">(.*?)</li>',
            r'<a class="video-item"[^>]*>(.*?)</a>',
            r'<div class="module-item">(.*?)</div>'
        ]
        
        blocks = []
        for pattern in patterns:
            matches = re.findall(pattern, html, re.S)
            if matches:
                blocks.extend(matches)
                break  # 使用第一个匹配的模式
        
        return blocks
    
    def extract_videos(self, html, category_name):
        """从HTML中提取视频信息"""
        videos = []
        
        # 提取视频块
        blocks = self.extract_video_blocks(html)
        
        print(f"在 {category_name} 中找到 {len(blocks)} 个视频块")
        
        for i, block in enumerate(blocks):
            # 提取标题
            title_match = re.search(r'title="([^"]+)"', block)
            if not title_match:
                title_match = re.search(r'title=\'([^\']+)\'', block)
            if not title_match:
                title_match = re.search(r'<img[^>]*alt="([^"]+)"', block)
            
            title = title_match.group(1) if title_match else f"未知标题_{i}"
            clean_title = self.get_clean_title(title)
            
            # 提取详情页链接
            link_match = re.search(r'href="([^"]+)"', block)
            if not link_match:
                link_match = re.search(r'href=\'([^\']+)\'', block)
            
            if not link_match:
                continue
                
            detail_url = urljoin(cfg["site"], link_match.group(1))
            
            # 获取详情页内容
            detail_html = self.fetch_with_retry(detail_url)
            if not detail_html:
                print(f"无法获取详情页: {detail_url}")
                continue
            
            # 从详情页中提取m3u8链接
            m3u8_urls = self.extract_m3u8_urls(detail_html)
            m3u8_url = self.get_best_m3u8_url(m3u8_urls)
            
            if m3u8_url:
                videos.append(f"{clean_title},{m3u8_url}")
                with self.lock: 
                    self.stats['videos'] += 1
                print(f"提取成功: {clean_title}")
            else:
                print(f"提取失败: {clean_title}")
        
        return videos
    
    def process_page(self, tid, tname, page_num):
        """处理单个页面"""
        if self.stop_flag: return []
        
        url = cfg["api"].format(tid=tid, pg=page_num)
        html = self.fetch_with_retry(url)
        if not html: return []
        
        videos = self.extract_videos(html, tname)
        with self.lock: self.stats['pages'] += 1
        
        print(f"{tname} 第{page_num}页: 提取到 {len(videos)} 个视频")
        time.sleep(cfg["delay"] + random.uniform(0, 0.5))  # 随机延迟避免被封
        return videos
    
    def process_category(self, tid, tname):
        """处理单个分类"""
        if self.stop_flag: return
        
        print(f"\n开始处理分类: {tname}")
        
        # 获取第一页以确定总页数
        first_page = self.fetch_with_retry(cfg["api"].format(tid=tid, pg=1))
        if not first_page:
            print(f"无法获取分类 {tname} 的第一页")
            return
        
        total_pages = self.get_total_pages(first_page)
        print(f"{tname} 共有 {total_pages} 页")
        
        # 使用多线程处理页面
        page_queue = queue.Queue()
        results_queue = queue.Queue()
        
        # 添加所有页面到队列
        for page_num in range(1, total_pages + 1):
            page_queue.put(page_num)
        
        def page_worker():
            while not self.stop_flag:
                try:
                    page_num = page_queue.get_nowait()
                    videos = self.process_page(tid, tname, page_num)
                    if videos: 
                        results_queue.put(videos)
                    page_queue.task_done()
                except queue.Empty:
                    break
        
        # 启动线程
        threads = []
        for _ in range(min(cfg["threads"], total_pages)):
            t = threading.Thread(target=page_worker)
            t.daemon = True
            t.start()
            threads.append(t)
        
        # 等待完成
        try:
            page_queue.join()
        except KeyboardInterrupt:
            self.stop_flag = True
        
        # 收集结果
        category_videos = []
        while not results_queue.empty():
            try:
                videos = results_queue.get_nowait()
                category_videos.extend(videos)
            except queue.Empty:
                break
        
        # 将结果添加到分类字典
        with self.lock:
            self.results[tname].extend(category_videos)
            self.stats['categories'] += 1
        
        print(f"完成分类: {tname}, 共提取 {len(category_videos)} 个视频")
        
        # 每个分类处理完后立即保存到文件
        self.save_results()
    
    def save_results(self):
        """保存结果到文件，添加分类标记"""
        if not any(self.results.values()): return False
        
        filename = '糖心.txt'
        try:
            with open(filename, 'w', encoding='utf-8') as f:
                for category, videos in self.results.items():
                    if videos:  # 只输出有视频的分类
                        f.write(f"{category},#genre#\n")
                        for video in videos:
                            f.write(f"{video}\n")
                        f.write("\n")  # 分类之间空一行
            
            total_videos = sum(len(videos) for videos in self.results.values())
            print(f"已保存: {filename}, 共{total_videos}视频")
            return True
        except Exception as e:
            print(f"保存错误: {e}")
            return False
    
    def run(self):
        """运行采集器"""
        print("开始采集...")
        start_time = time.time()
        
        # 检查网络连接
        try:
            test_response = requests.get(cfg["site"], timeout=10)
            if test_response.status_code != 200:
                print("❌ 网站无法访问，请检查网络连接")
                return
        except:
            print("❌ 网络连接失败，请检查网络设置")
            return
        
        # 获取分类
        types = cfg["types"].split('&')
        names = cfg["names"].split('&')
        
        if len(types) != len(names):
            print("❌ 配置错误: types和names数量不匹配")
            return
        
        print(f"共有 {len(types)} 个分类需要处理")
        
        # 处理每个分类
        for i, (tid, tname) in enumerate(zip(types, names)):
            if self.stop_flag: break
            print(f"\n处理分类 {i+1}/{len(types)}: {tname}")
            self.process_category(tid, tname)
        
        # 最终保存结果
        self.save_results()
        elapsed = time.time() - start_time
        total_videos = sum(len(videos) for videos in self.results.values())
        print(f"\n采集完成!")
        print(f"统计: {self.stats['categories']}分类, {self.stats['pages']}页面, {total_videos}视频")
        print(f"重试次数: {self.stats['retries']}")
        print(f"总耗时: {elapsed:.1f}秒")

if __name__ == "__main__":
    import signal
    import sys
    
    fetcher = EnhancedVideoFetcher()
    
    def signal_handler(signum, frame):
        print("\n用户中断，正在保存已采集的数据...")
        fetcher.stop_flag = True
        fetcher.save_results()
        sys.exit(0)
    
    signal.signal(signal.SIGINT, signal_handler)
    fetcher.run()