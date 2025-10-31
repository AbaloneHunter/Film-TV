<?php
/**
 * TVBox PHP 爬虫脚本 - 糖心次元版 (兼容Apple CMS API格式)
 * 支持二级分类功能和自定义样式
 * 
 * 二级分类实现说明：
 * 1. 当返回的JSON根级包含 'is_sub' => true 时，表示当前列表是二级分类
 * 2. 二级分类项目会被标记为文件夹图标，点击后会重新调用categoryContent
 * 3. 点击二级分类时，会在筛选参数f中包含 'is_sub' => 'true'
 * 4. 根据is_sub参数判断是返回二级分类还是实际内容
 * 
 * 自定义样式说明：
 * 1. style字段应放在JSON最外层，会应用到所有列表项
 * 2. type: 'rect'(矩形), 'oval'(椭圆), 'round'(圆形)
 * 3. ratio: 宽高比例，如1.5表示3:2，0.67表示2:3
 */

header('Content-Type: application/json; charset=utf-8');

// 使用Apple CMS标准参数
$ac = $_GET['ac'] ?? 'detail';  // 操作类型
$t = $_GET['t'] ?? '';          // 类型ID
$pg = $_GET['pg'] ?? '1';       // 页码
$f = $_GET['f'] ?? '';          // 筛选条件JSON
$ids = $_GET['ids'] ?? '';      // 详情ID
$wd = $_GET['wd'] ?? '';        // 搜索关键词
$flag = $_GET['flag'] ?? '';    // 播放标识
$id = $_GET['id'] ?? '';        // 播放ID

switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            // 视频详情
            echo json_encode(getDetail($ids));
        } elseif (!empty($t)) {
            // 分类列表
            $filters = !empty($f) ? json_decode($f, true) : [];
            
            // 检查是否请求二级分类
            $isSubRequest = isset($filters['is_sub']) && $filters['is_sub'] === 'true';
            
            if ($isSubRequest) {
                // 二级分类：返回实际内容
                echo json_encode(getCategory($t, $pg));
            } else {
                // 检查是否需要返回二级分类
                $result = getHome();
                if (isset($result['is_sub'])) {
                    // 返回二级分类列表
                    echo json_encode($result);
                } else {
                    // 普通分类列表
                    echo json_encode(getCategory($t, $pg));
                }
            }
        } else {
            // 首页分类
            echo json_encode(getHome());
        }
        break;
    
    case 'search':
        echo json_encode(search($wd, $pg));
        break;
        
    case 'play':
        echo json_encode(getPlay($flag, $id));
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $ac]);
}

class TangXinSpider {
    private $base = "https://www.txsp.my";
    private $img_base = "https://img1.souavzy.org";
    private $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: zh-CN,zh;q=0.9',
        'Referer: https://www.txsp.my/',
        'Origin: https://www.txsp.my'
    ];
    
    private $category_map = [
        "1" => "传媒系列",
        "2" => "AV系列",
        "5" => "麻豆传媒",
        "6" => "糖心传媒",
        "7" => "精东影业",
        "8" => "蜜桃传媒",
        "9" => "果冻传媒",
        "10" => "星空无限",
        "11" => "天美传媒",
        "12" => "抠抠传媒",
        "13" => "星杏吧传媒",
        "14" => "性视界传媒",
        "15" => "SA国际传媒",
        "16" => "其他传媒",
        "17" => "国产-自拍-偷拍",
        "18" => "探花-主播-网红",
        "19" => "日本-中文字幕",
        "20" => "日本-无码流出",
        "21" => "日本-高清有码",
        "22" => "日本-东京热",
        "23" => "动漫-番中字",
        "24" => "变态-暗网-同恋",
        "25" => "欧美高清无码",
        "27" => "韩国av"
    ];
    
    // 二级分类映射 - 主分类ID到二级分类数组
    private $sub_categories = [
        "1" => [ // 传媒系列下的二级分类
            ["vod_id" => "5", "vod_name" => "麻豆传媒", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "6", "vod_name" => "糖心传媒", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "7", "vod_name" => "精东影业", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "8", "vod_name" => "蜜桃传媒", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "9", "vod_name" => "果冻传媒", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "10", "vod_name" => "星空无限", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "11", "vod_name" => "天美传媒", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "12", "vod_name" => "抠抠传媒", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "13", "vod_name" => "星杏吧传媒", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "14", "vod_name" => "性视界传媒", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "15", "vod_name" => "SA国际传媒", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "16", "vod_name" => "其他传媒", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"]
        ],
        "2" => [ // AV系列下的二级分类
            ["vod_id" => "17", "vod_name" => "国产-自拍-偷拍", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "18", "vod_name" => "探花-主播-网红", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "19", "vod_name" => "日本-中文字幕", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "20", "vod_name" => "日本-无码流出", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "21", "vod_name" => "日本-高清有码", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "22", "vod_name" => "日本-东京热", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "23", "vod_name" => "动漫-番中字", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "24", "vod_name" => "变态-暗网-同恋", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"],
            ["vod_id" => "25", "vod_name" => "欧美高清无码", "vod_pic" => "https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg"],
            ["vod_id" => "27", "vod_name" => "韩国av", "vod_pic" => "https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg"]
        ]
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
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        return $ch;
    }

    public function fetch($url) {
        curl_setopt($this->session, CURLOPT_URL, $url);
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    /**
     * 去除韩国AV标题前缀 (例如: kbj-23010421标题 -> 标题)
     */
    private function cleanTitle($name) {
        return preg_replace('/^[a-zA-Z]{2,}\-\d+\s*/', '', trim($name));
    }

    /**
     * 处理图片URL
     */
    private function processImageUrl($img) {
        if (empty($img)) {
            return '';
        }
        
        if (strpos($img, 'http') === 0) {
            return $img;
        }
        
        if (strpos($img, '//') === 0) {
            return 'https:' . $img;
        }
        
        return $this->img_base . $img;
    }

    /**
     * 处理链接URL
     */
    private function processLinkUrl($link) {
        if (empty($link)) {
            return '';
        }
        
        if (strpos($link, 'http') === 0) {
            return $link;
        }
        
        return $this->base . $link;
    }

    /**
     * 通用解析视频列表
     */
    private function parseVideoList($html) {
        $videos = [];
        
        if (empty($html)) {
            return $videos;
        }
        
        // 使用 DOMDocument 解析 HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 查找所有包含视频的 li 元素
        $items = $xpath->query('//li[contains(@class, "mb15") and .//a[contains(@href, "/vod/play/")]]');
        
        foreach ($items as $item) {
            // 提取标题
            $titleNodes = $xpath->query('.//h2/a/@title | .//h3/a/@title | .//p[contains(@class, "txt-ov")]/text()', $item);
            $name = $titleNodes->length > 0 ? trim($titleNodes->item(0)->nodeValue) : '';
            $name = $this->cleanTitle($name);
            
            // 提取图片
            $imgNodes = $xpath->query('.//img/@src', $item);
            $img = $imgNodes->length > 0 ? $imgNodes->item(0)->nodeValue : '';
            $img = $this->processImageUrl($img);
            
            // 提取链接
            $linkNodes = $xpath->query('.//a[contains(@href, "/vod/play/")]/@href', $item);
            $link = $linkNodes->length > 0 ? $linkNodes->item(0)->nodeValue : '';
            $link = $this->processLinkUrl($link);
            
            // 提取备注信息
            $remarksNodes = $xpath->query('.//span[contains(@class, "ico-left")]/text()', $item);
            $remarks = $remarksNodes->length > 0 ? trim($remarksNodes->item(0)->nodeValue) : '';
            
            if (!empty($link) && !empty($name)) {
                $videos[] = [
                    'vod_id' => $link,
                    'vod_name' => $name ?: '未知标题',
                    'vod_pic' => $img,
                    'vod_remarks' => $remarks
                ];
            }
        }
        
        return $videos;
    }

    /**
     * 提取播放地址
     */
    private function extractPlayUrl($html) {
        // 修复转义的斜杠
        $html = str_replace('\\/', '/', $html);
        
        // 尝试多种正则模式提取 player_aaaa 或 player_data
        $patterns = [
            '/var\s+player_aaaa\s*=\s*(\{[^\}]+\})/',
            '/player_aaaa\s*=\s*(\{[^\}]+\})/',
            '/var\s+player_data\s*=\s*(\{[^\}]+\})/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                try {
                    $jsonStr = $matches[1];
                    $data = json_decode($jsonStr, true);
                    
                    if (isset($data['url']) && !empty($data['url'])) {
                        return $data['url'];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 尝试从 iframe src 提取
        if (preg_match('/<iframe[^>]+src="([^"]+souavzy[^"]+)"/i', $html, $matches)) {
            $src = $matches[1];
            if (preg_match('/url=([^&]+)/', $src, $urlMatch)) {
                return urldecode($urlMatch[1]);
            }
        }
        
        // 尝试直接匹配 m3u8 URL
        if (preg_match_all('/"(https?:\/\/[^"]+\.m3u8[^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if (strpos($url, 'souavzy') !== false || strpos($url, 'qrtuv') !== false) {
                    return $url;
                }
            }
        }
        
        return '';
    }

    /**
     * 首页内容
     */
    public function getHomeContent() {
        try {
            $categories = [];
            foreach ($this->category_map as $id => $name) {
                $categories[] = [
                    'type_id' => $id,
                    'type_name' => $name
                ];
            }
            
            return [
                'class' => $categories,
                // 添加筛选条件示例
                'filters' => [
                    '1' => [
                        ['key' => 'class', 'name' => '类型', 'value' => [
                            ['n' => '全部', 'v' => ''],
                            ['n' => '热门', 'v' => 'hot'],
                            ['n' => '推荐', 'v' => 'recommend']
                        ]]
                    ]
                ],
                // 自定义样式
                'style' => [
                    'type' => 'rect',
                    'ratio' => 1.5
                ]
            ];
        } catch (Exception $e) {
            return ['class' => [], 'list' => []];
        }
    }

    /**
     * 分类内容
     */
    public function getCategoryContent($tid, $pg) {
        try {
            // 检查是否是二级分类的主分类（需要返回二级分类列表）
            if (isset($this->sub_categories[$tid]) && $pg == '1') {
                return [
                    'is_sub' => true,   // 标识这是二级分类列表
                    'list' => $this->sub_categories[$tid],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => count($this->sub_categories[$tid]),
                    'total' => count($this->sub_categories[$tid]),
                    // 文件夹样式：适合二级分类导航
                    'style' => [
                        'type' => 'rect',
                        'ratio' => 1.8
                    ]
                ];
            }
            
            $url = $pg == '1' 
                ? "{$this->base}/index.php/vod/type/id/{$tid}.html"
                : "{$this->base}/index.php/vod/type/id/{$tid}/page/{$pg}.html";
            
            $html = $this->fetch($url);
            if (!$html) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 0,
                    'total' => 0
                ];
            }
            
            $videos = $this->parseVideoList($html);
            
            // 提取总页数
            preg_match_all('/\/page\/(\d+)/', $html, $pageMatches);
            $pagecount = !empty($pageMatches[1]) ? max(array_map('intval', $pageMatches[1])) : 1;
            
            return [
                'list' => $videos,
                'page' => intval($pg),
                'pagecount' => $pagecount,
                'limit' => count($videos),
                'total' => 999999,
                // 可选：为内容设置不同的样式
                'style' => [
                    'type' => 'rect',
                    'ratio' => 1.33
                ]
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'limit' => 0,
                'total' => 0
            ];
        }
    }

    /**
     * 详情内容
     */
    public function getDetailContent($ids) {
        $result = ['list' => []];
        
        foreach ($ids as $id) {
            try {
                $url = strpos($id, 'http') === 0 ? $id : $this->base . $id;
                
                $html = $this->fetch($url);
                if (!$html) {
                    $result['list'][] = [
                        'vod_id' => $id,
                        'vod_name' => '获取失败',
                        'vod_pic' => '',
                        'vod_content' => '网络请求失败'
                    ];
                    continue;
                }
                
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                
                // 提取标题
                $titleNodes = $xpath->query('//h1/text()');
                $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->nodeValue) : '未知标题';
                
                // 提取封面
                $picNodes = $xpath->query('//meta[@property="og:image"]/@content | //img[contains(@src, "upload/vod")]/@src');
                $pic = $picNodes->length > 0 ? $picNodes->item(0)->nodeValue : '';
                $pic = $this->processImageUrl($pic);
                
                // 提取播放地址
                $playUrl = $this->extractPlayUrl($html);
                
                $result['list'][] = [
                    'vod_id' => $id,
                    'vod_name' => $title,
                    'vod_pic' => $pic,
                    'vod_content' => $title,
                    'vod_play_from' => '糖心次元',
                    'vod_play_url' => $playUrl ? '播放$' . $playUrl : '播放$暂无播放地址'
                ];
                
            } catch (Exception $e) {
                $result['list'][] = [
                    'vod_id' => $id,
                    'vod_name' => '获取失败',
                    'vod_pic' => '',
                    'vod_content' => '获取详情失败: ' . $e->getMessage()
                ];
            }
        }
        
        return $result;
    }

    /**
     * 搜索内容
     */
    public function getSearchContent($keyword, $pg = '1') {
        try {
            $encodedKeyword = urlencode($keyword);
            $url = "{$this->base}/index.php/vod/search/page/{$pg}/wd/{$encodedKeyword}.html";
            
            $html = $this->fetch($url);
            if (!$html) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 0,
                    'total' => 0
                ];
            }
            
            $videos = $this->parseVideoList($html);
            
            return [
                'list' => $videos,
                'page' => intval($pg),
                'pagecount' => 999,
                'limit' => count($videos),
                'total' => 999999
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'limit' => 0,
                'total' => 0
            ];
        }
    }

    /**
     * 播放内容
     */
    public function getPlayerContent($flag, $id) {
        try {
            // 如果不是糖心次元线路,返回空
            if ($flag !== '糖心次元') {
                return ['parse' => 0, 'playUrl' => '', 'url' => ''];
            }
            
            // 如果id已经是完整的播放URL
            if (strpos($id, 'http') === 0 && (strpos($id, '.m3u8') !== false || strpos($id, 'souavzy') !== false)) {
                return [
                    'parse' => 0,
                    'playUrl' => '',
                    'url' => $id,
                    'header' => [
                        'User-Agent' => 'Mozilla/5.0',
                        'Referer' => 'https://www.txsp.my/',
                        'Origin' => 'https://www.txsp.my'
                    ]
                ];
            }
            
            // 否则需要获取详情页提取播放地址
            $url = strpos($id, 'http') === 0 ? $id : $this->base . $id;
            $html = $this->fetch($url);
            
            if ($html) {
                $playUrl = $this->extractPlayUrl($html);
                
                if ($playUrl) {
                    return [
                        'parse' => 0,
                        'playUrl' => '',
                        'url' => $playUrl,
                        'header' => [
                            'User-Agent' => 'Mozilla/5.0',
                            'Referer' => 'https://www.txsp.my/',
                            'Origin' => 'https://www.txsp.my'
                        ]
                    ];
                }
            }
            
            // 如果提取失败,尝试使用解析
            return [
                'parse' => 1,
                'playUrl' => '',
                'url' => $id,
                'header' => [
                    'User-Agent' => 'Mozilla/5.0',
                    'Referer' => 'https://www.txsp.my/',
                    'Origin' => 'https://www.txsp.my'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'parse' => 0,
                'playUrl' => '',
                'url' => '',
                'header' => []
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
    $spider = new TangXinSpider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 */
function getCategory($tid, $page) {
    $spider = new TangXinSpider();
    return $spider->getCategoryContent($tid, $page);
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $spider = new TangXinSpider();
    return $spider->getDetailContent(explode(',', $ids));
}

/**
 * 搜索
 */
function search($keyword, $page) {
    $spider = new TangXinSpider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 */
function getPlay($flag, $id) {
    $spider = new TangXinSpider();
    return $spider->getPlayerContent($flag, $id);
}
?>