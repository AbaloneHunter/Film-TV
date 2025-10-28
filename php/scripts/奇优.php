<?php
/**
 * TVBox PHP 爬虫脚本 - 奇优影院版
 * 支持的 action: home, category, detail, search, play
 */

// 获取请求参数
$action = $_GET['action'] ?? '';

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 根据不同 action 返回数据
switch ($action) {
    case 'home':
        echo json_encode(getHome());
        break;
    
    case 'category':
        $tid = $_GET['t'] ?? '';
        $page = $_GET['pg'] ?? '1';
        $by = $_GET['by'] ?? 'time';
        echo json_encode(getCategory($tid, $page, $by));
        break;
    
    case 'detail':
        $ids = $_GET['ids'] ?? '';
        echo json_encode(getDetail($ids));
        break;
    
    case 'search':
        $keyword = $_GET['wd'] ?? '';
        $page = $_GET['pg'] ?? '1';
        echo json_encode(search($keyword, $page));
        break;
    
    case 'play':
        $flag = $_GET['flag'] ?? '';
        $id = $_GET['id'] ?? '';
        echo json_encode(getPlay($flag, $id));
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action']);
}

class QiYouSpider {
    private $base = "http://qiyoudy5.com";
    private $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: zh-CN,zh;q=0.9,zh-TW;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive'
    ];
    
    private $cateManual = [
        "电影" => "1",
        "电视剧" => "2",
        "动漫" => "3",
        "综艺" => "4",
        "午夜" => "6"
    ];
    
    private $session;

    public function __construct() {
        $this->session = $this->createSession();
    }

    private function createSession() {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $this->headers
        ]);
        return $ch;
    }

    public function fetch($url) {
        curl_setopt($this->session, CURLOPT_URL, $url);
        curl_setopt($this->session, CURLOPT_POST, false);
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    private function postRequest($url, $data) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer: ' . $this->base . '/',
                'Origin: ' . $this->base,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    private function parseHtml($html) {
        if (empty($html)) {
            return null;
        }
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->encoding = 'UTF-8';
        
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        return new DOMXPath($dom);
    }

    private function xpathQuery($xpath, $query, $node = null) {
        if (!$xpath) {
            return [];
        }
        try {
            if ($node) {
                $result = $xpath->query($query, $node);
            } else {
                $result = $xpath->query($query);
            }
            $items = [];
            if ($result) {
                foreach ($result as $item) {
                    $items[] = $item;
                }
            }
            return $items;
        } catch (Exception $e) {
            return [];
        }
    }

    private function xpathValue($xpath, $query, $node = null) {
        $result = $this->xpathQuery($xpath, $query, $node);
        if (!empty($result) && $result[0]) {
            return $result[0]->nodeValue;
        }
        return '';
    }

    private function getAttr($node, $attr) {
        if ($node && $node->hasAttribute($attr)) {
            return $node->getAttribute($attr);
        }
        return '';
    }

    public function getHomeContent() {
        try {
            $url = $this->base . "/";
            $html = $this->fetch($url);
            if (!$html) {
                return ['class' => [], 'list' => []];
            }
            
            $xpath = $this->parseHtml($html);
            if (!$xpath) {
                return ['class' => [], 'list' => []];
            }
            
            $categories = [];
            foreach ($this->cateManual as $name => $id) {
                $categories[] = [
                    'type_id' => $id,
                    'type_name' => $name
                ];
            }
            
            $videos = [];
            
            // 轮播图
            $carouselItems = $this->xpathQuery($xpath, "//div[contains(@class,'carousel')]//a[contains(@class,'stui-vodlist__thumb')]");
            foreach ($carouselItems as $item) {
                try {
                    $name_nodes = $this->xpathQuery($xpath, ".//span[@class='pic-text text-center']/text()", $item);
                    $name = '';
                    if (!empty($name_nodes)) {
                        $name = trim($name_nodes[0]->nodeValue);
                    }
                    if (!$name) {
                        $name = $this->getAttr($item, 'title');
                    }
                    if (!$name) {
                        $name = '未知';
                    }
                    
                    $style = $this->getAttr($item, 'style');
                    $pic = '';
                    if (preg_match('/background:\s*url\((.*?)\)/', $style, $matches)) {
                        $pic = $matches[1];
                    }
                    
                    $href = $this->getAttr($item, 'href');
                    
                    if ($href) {
                        $videos[] = [
                            'vod_id' => $href,
                            'vod_name' => $name,
                            'vod_pic' => $pic,
                            'vod_remarks' => '推荐'
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            // 视频列表
            $listItems = $this->xpathQuery($xpath, "//ul[contains(@class,'stui-vodlist')]//a[contains(@class,'stui-vodlist__thumb')]");
            foreach ($listItems as $item) {
                try {
                    $name = $this->getAttr($item, 'title');
                    if (!$name) {
                        $name = '未知';
                    }
                    
                    $pic = $this->getAttr($item, 'data-original');
                    $href = $this->getAttr($item, 'href');
                    
                    $remark_nodes = $this->xpathQuery($xpath, ".//span[@class='pic-text text-right']/text()", $item);
                    $remark = '';
                    if (!empty($remark_nodes)) {
                        $remark = $remark_nodes[0]->nodeValue;
                    }
                    
                    if ($href) {
                        $videos[] = [
                            'vod_id' => $href,
                            'vod_name' => $name,
                            'vod_pic' => $pic,
                            'vod_remarks' => $remark
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            return [
                'class' => $categories,
                'list' => $videos
            ];
        } catch (Exception $e) {
            return ['class' => [], 'list' => []];
        }
    }

    public function getCategoryContent($tid, $pg, $by = 'time') {
        try {
            $url = $this->base . "/list/" . $tid . "_" . $pg . ".html?order=" . $by;
            $html = $this->fetch($url);
            
            if (!$html) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 90,
                    'total' => 0
                ];
            }
            
            $xpath = $this->parseHtml($html);
            if (!$xpath) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 90,
                    'total' => 0
                ];
            }
            
            $videos = [];
            $items = $this->xpathQuery($xpath, "//a[contains(@class,'stui-vodlist__thumb')]");
            
            foreach ($items as $item) {
                try {
                    $name = $this->getAttr($item, 'title');
                    if (!$name) {
                        $name = '未知';
                    }
                    
                    $pic = $this->getAttr($item, 'data-original');
                    $href = $this->getAttr($item, 'href');
                    
                    $remark_nodes = $this->xpathQuery($xpath, ".//span[@class='pic-text text-right']/text()", $item);
                    $remark = '';
                    if (!empty($remark_nodes)) {
                        $remark = $remark_nodes[0]->nodeValue;
                    }
                    
                    if ($href) {
                        $videos[] = [
                            'vod_id' => $href,
                            'vod_name' => $name,
                            'vod_pic' => $pic,
                            'vod_remarks' => $remark
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            // 解析分页信息
            $pagecount = 1;
            $current_page = intval($pg);
            
            $active_pages = $this->xpathQuery($xpath, "//ul[contains(@class,'stui-page')]//a[@class='active']/text()");
            if (!empty($active_pages)) {
                $current_page = intval($active_pages[0]->nodeValue);
            }
            
            $page_links = $this->xpathQuery($xpath, "//ul[contains(@class,'stui-page')]//a[contains(@href,'list')]/@href");
            $page_numbers = [];
            foreach ($page_links as $link_node) {
                $link = $link_node->nodeValue;
                if (preg_match('/list\/\d+_(\d+)\.html/', $link, $matches)) {
                    $page_numbers[] = intval($matches[1]);
                }
            }
            
            if (!empty($page_numbers)) {
                $pagecount = max($page_numbers);
            }
            
            if ($pagecount <= 0) {
                $pagecount = 9999;
            }
            
            return [
                'list' => $videos,
                'page' => $current_page,
                'pagecount' => $pagecount,
                'limit' => 90,
                'total' => 999999
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'limit' => 90,
                'total' => 0
            ];
        }
    }

    public function getDetailContent($ids) {
        $result = ['list' => []];
        
        foreach ($ids as $tid) {
            try {
                $url = $this->base . $tid;
                $html = $this->fetch($url);
                
                if (!$html) {
                    $result['list'][] = [
                        'vod_id' => $tid,
                        'vod_name' => '获取失败',
                        'vod_pic' => '',
                        'vod_content' => '网络请求失败'
                    ];
                    continue;
                }
                
                $xpath = $this->parseHtml($html);
                if (!$xpath) {
                    $result['list'][] = [
                        'vod_id' => $tid,
                        'vod_name' => '获取失败',
                        'vod_pic' => '',
                        'vod_content' => 'HTML解析失败'
                    ];
                    continue;
                }
                
                // 获取基本信息
                $title = '';
                $detail_nodes = $this->xpathQuery($xpath, "//div[contains(@class,'stui-content__detail')] | //div[@class='stui-player__detail']");
                
                if (!empty($detail_nodes)) {
                    $h1_text = $this->xpathValue($xpath, ".//h1//text()", $detail_nodes[0]);
                    if ($h1_text) {
                        $title = $h1_text;
                    }
                }
                
                if (!$title) {
                    $page_title = $this->xpathValue($xpath, "//title/text()");
                    if ($page_title && preg_match('/《(.*?)》/', $page_title, $matches)) {
                        $title = $matches[1];
                    }
                }
                
                if (!$title) {
                    $title = '视频_' . $tid;
                }
                
                // 获取图片
                $pic = $this->xpathValue($xpath, "//meta[@property='og:image']/@content");
                if (!$pic && !empty($detail_nodes)) {
                    $pic = $this->xpathValue($xpath, ".//img/@data-original", $detail_nodes[0]);
                }
                
                // 获取其他信息
                $area = $this->xpathValue($xpath, "//meta[@property='og:video:area']/@content");
                $director = $this->xpathValue($xpath, "//meta[@property='og:video:director']/@content");
                $actor = $this->xpathValue($xpath, "//meta[@property='og:video:actor']/@content");
                
                $year = '';
                $year_info = $this->xpathValue($xpath, "//p[@class='data']//text()[contains(.,'年份：')]");
                if ($year_info && preg_match('/年份：(\d{4})/', $year_info, $matches)) {
                    $year = $matches[1];
                }
                
                $desc = $this->xpathValue($xpath, "//meta[@property='og:description']/@content");
                
                // 修复:正确解析播放列表
                $playFrom = [];
                $playUrl = [];
                
                $tabs = $this->xpathQuery($xpath, "//ul[contains(@class,'nav-tabs')]/li");
                foreach ($tabs as $tab) {
                    $tab_name = $this->xpathValue($xpath, ".//a/text()", $tab);
                    $tab_href = $this->xpathValue($xpath, ".//a/@href", $tab);
                    
                    if (!$tab_href) {
                        continue;
                    }
                    
                    $tab_id = str_replace("#", "", $tab_href);
                    
                    if ($tab_name && $tab_id) {
                        $play_list = $this->xpathQuery($xpath, "//div[@id='" . $tab_id . "']//ul[contains(@class,'stui-content__playlist')]//a");
                        
                        if (!empty($play_list)) {
                            $playFrom[] = $tab_name;
                            $episodes = [];
                            
                            foreach ($play_list as $episode_node) {
                                $ep_name = '';
                                $text_nodes = $this->xpathQuery($xpath, "./text()", $episode_node);
                                if (!empty($text_nodes)) {
                                    $ep_name = trim($text_nodes[0]->nodeValue);
                                }
                                
                                if (!$ep_name) {
                                    $ep_name = '播放';
                                }
                                
                                $ep_url = $this->getAttr($episode_node, 'href');
                                
                                if ($ep_url) {
                                    $episodes[] = $ep_name . '$' . $ep_url;
                                }
                            }
                            
                            if (!empty($episodes)) {
                                $playUrl[] = implode('#', $episodes);
                            }
                        }
                    }
                }
                
                // 备选方案
                if (empty($playFrom) || empty($playUrl)) {
                    $play_lists = $this->xpathQuery($xpath, "//ul[contains(@class,'stui-content__playlist')]");
                    
                    if (!empty($play_lists)) {
                        $playFrom = ['默认播放源'];
                        $episodes = [];
                        
                        foreach ($play_lists as $play_list) {
                            $links = $this->xpathQuery($xpath, ".//a", $play_list);
                            foreach ($links as $link) {
                                $ep_name = '';
                                $text_nodes = $this->xpathQuery($xpath, "./text()", $link);
                                if (!empty($text_nodes)) {
                                    $ep_name = trim($text_nodes[0]->nodeValue);
                                }
                                
                                if (!$ep_name) {
                                    $ep_name = '播放';
                                }
                                
                                $ep_url = $this->getAttr($link, 'href');
                                
                                if ($ep_url) {
                                    $episodes[] = $ep_name . '$' . $ep_url;
                                }
                            }
                        }
                        
                        if (!empty($episodes)) {
                            $playUrl = [implode('#', $episodes)];
                        }
                    }
                }
                
                $vod = [
                    'vod_id' => $tid,
                    'vod_name' => $title,
                    'vod_pic' => $pic,
                    'vod_year' => $year,
                    'vod_area' => $area,
                    'vod_actor' => $actor,
                    'vod_director' => $director,
                    'vod_content' => $desc
                ];
                
                if (!empty($playFrom) && !empty($playUrl)) {
                    $vod['vod_play_from'] = implode('$$$', $playFrom);
                    $vod['vod_play_url'] = implode('$$$', $playUrl);
                }
                
                $result['list'][] = $vod;
                
            } catch (Exception $e) {
                $result['list'][] = [
                    'vod_id' => $tid,
                    'vod_name' => '获取失败',
                    'vod_pic' => '',
                    'vod_content' => '获取详情失败: ' . $e->getMessage()
                ];
            }
        }
        
        return $result;
    }

    public function getSearchContent($key, $pg = 1) {
        try {
            $url = $this->base . "/search.php";
            
            // 使用POST请求
            $post_data = [
                'searchword' => $key,
                'key' => $key
            ];
            
            $html = $this->postRequest($url, $post_data);
            
            if (!$html) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'total' => 0
                ];
            }
            
            $xpath = $this->parseHtml($html);
            if (!$xpath) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'total' => 0
                ];
            }
            
            $videos = [];
            $items = $this->xpathQuery($xpath, "//a[contains(@class,'stui-vodlist__thumb')]");
            
            foreach ($items as $item) {
                try {
                    $name = $this->getAttr($item, 'title');
                    if (!$name) {
                        $name = '未知';
                    }
                    
                    $pic = $this->getAttr($item, 'data-original');
                    $href = $this->getAttr($item, 'href');
                    
                    $remark_nodes = $this->xpathQuery($xpath, ".//span[@class='pic-text text-right']/text()", $item);
                    $remark = '';
                    if (!empty($remark_nodes)) {
                        $remark = $remark_nodes[0]->nodeValue;
                    }
                    
                    if ($href) {
                        $videos[] = [
                            'vod_id' => $href,
                            'vod_name' => $name,
                            'vod_pic' => $pic,
                            'vod_remarks' => $remark
                        ];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            return [
                'list' => $videos,
                'page' => intval($pg),
                'pagecount' => 1,
                'total' => count($videos)
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'total' => 0
            ];
        }
    }

    public function getPlayerContent($id) {
        try {
            if (strpos($id, 'http') === 0) {
                return [
                    'parse' => 0,
                    'playUrl' => '',
                    'url' => $id,
                    'header' => [
                        'Referer' => $this->base,
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ];
            }
            
            $url = $this->base . $id;
            $html = $this->fetch($url);
            
            if ($html) {
                // 查找API链接
                if (preg_match('/http:\/\/api\.yongfan99\.com:81\/content\.php\?[^\'\"]+/', $html, $matches)) {
                    $api_url = $matches[0];
                    $api_response = $this->fetch($api_url);
                    
                    if ($api_response && preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $api_response, $m3u8_match)) {
                        return [
                            'parse' => 0,
                            'playUrl' => '',
                            'url' => $m3u8_match[0],
                            'header' => [
                                'Referer' => $url,
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                            ]
                        ];
                    }
                }
                
                // 查找iframe
                if (preg_match_all('/<iframe[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                    foreach ($matches[1] as $iframe_src) {
                        if (!preg_match('/^http/', $iframe_src)) {
                            $iframe_src = $this->base . $iframe_src;
                        }
                        
                        $iframe_html = $this->fetch($iframe_src);
                        if ($iframe_html && preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $iframe_html, $m3u8_match)) {
                            return [
                                'parse' => 0,
                                'playUrl' => '',
                                'url' => $m3u8_match[0],
                                'header' => [
                                    'Referer' => $iframe_src,
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                ]
                            ];
                        }
                    }
                }
                
                // 直接搜索m3u8
                if (preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $html, $m3u8_match)) {
                    return [
                        'parse' => 0,
                        'playUrl' => '',
                        'url' => $m3u8_match[0],
                        'header' => [
                            'Referer' => $url,
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ]
                    ];
                }
            }
            
            return [
                'parse' => 1,
                'playUrl' => '',
                'url' => $url,
                'header' => [
                    'Referer' => $this->base,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'parse' => 1,
                'playUrl' => '',
                'url' => $this->base . $id,
                'header' => [
                    'Referer' => $this->base,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ];
        }
    }

    public function __destruct() {
        if ($this->session) {
            curl_close($this->session);
        }
    }
}

/**
 * 首页数据
 */
function getHome() {
    $spider = new QiYouSpider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 */
function getCategory($tid, $page, $by = 'time') {
    $spider = new QiYouSpider();
    return $spider->getCategoryContent($tid, $page, $by);
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $spider = new QiYouSpider();
    $idArray = explode(',', $ids);
    return $spider->getDetailContent($idArray);
}

/**
 * 搜索
 */
function search($keyword, $page) {
    $spider = new QiYouSpider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 */
function getPlay($flag, $id) {
    $spider = new QiYouSpider();
    return $spider->getPlayerContent($id);
}
?>