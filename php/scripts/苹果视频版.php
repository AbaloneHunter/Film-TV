<?php
/**
 * TVBox PHP 爬虫脚本 - 苹果视频版
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
        $extend = $_GET['extend'] ?? [];
        if (is_string($extend)) {
            parse_str($extend, $extend);
        }
        echo json_encode(getCategory($tid, $page, $extend));
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

class AppleVideoSpider {
    private $host = "https://618041.xyz";
    private $apiHost = "https://h5.xxoo168.org";
    private $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
        'Referer: https://618041.xyz'
    ];
    
    private $specialCategories = ['13', '14', '40', '9'];
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

    public function fetch($url, $headers = []) {
        curl_setopt($this->session, CURLOPT_URL, $url);
        if (!empty($headers)) {
            curl_setopt($this->session, CURLOPT_HTTPHEADER, array_merge($this->headers, $headers));
        }
        
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    private function html($content) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        return new DOMXPath($dom);
    }

    private function regStr($pattern, $string, $index = 1) {
        if (preg_match($pattern, $string, $matches)) {
            if (isset($matches[$index])) {
                return $matches[$index];
            }
        }
        return "";
    }

    public function getHomeContent() {
        $classes = [
            ['type_id' => '618041.xyz_1', 'type_name' => '全部视频'],
            ['type_id' => '618041.xyz_13', 'type_name' => '香蕉精品'],
            ['type_id' => '618041.xyz_22', 'type_name' => '制服诱惑'],
            ['type_id' => '618041.xyz_6', 'type_name' => '国产视频'],
            ['type_id' => '618041.xyz_8', 'type_name' => '清纯少女'],
            ['type_id' => '618041.xyz_9', 'type_name' => '辣妹大奶'],
            ['type_id' => '618041.xyz_10', 'type_name' => '女同专属'],
            ['type_id' => '618041.xyz_11', 'type_name' => '素人出演'],
            ['type_id' => '618041.xyz_12', 'type_name' => '角色扮演'],
            ['type_id' => '618041.xyz_20', 'type_name' => '人妻熟女'],
            ['type_id' => '618041.xyz_23', 'type_name' => '日韩剧情'],
            ['type_id' => '618041.xyz_21', 'type_name' => '经典伦理'],
            ['type_id' => '618041.xyz_7', 'type_name' => '成人动漫'],
            ['type_id' => '618041.xyz_14', 'type_name' => '精品二区'],
            ['type_id' => '618041.xyz_53', 'type_name' => '动漫中字'],
            ['type_id' => '618041.xyz_52', 'type_name' => '日本无码'],
            ['type_id' => '618041.xyz_33', 'type_name' => '中文字幕'],
            ['type_id' => '618041.xyz_44', 'type_name' => '国产传媒'],
            ['type_id' => '618041.xyz_32', 'type_name' => '国产自拍']
        ];

        $result = ['class' => $classes, 'list' => []];

        try {
            $rsp = $this->fetch($this->host);
            if ($rsp) {
                $doc = $this->html($rsp);
                $videos = $this->getVideos($doc, 20);
                $result['list'] = $videos;
            }
        } catch (Exception $e) {
            // 记录错误但不影响返回
        }

        return $result;
    }

    public function getCategoryContent($tid, $pg, $extend) {
        try {
            list($domain, $typeId) = explode('_', $tid);
            $url = "https://{$domain}/index.php/vod/type/id/{$typeId}.html";
            
            if ($pg != '1') {
                $url = str_replace('.html', "/page/{$pg}.html", $url);
            }

            $rsp = $this->fetch($url);
            if (!$rsp) {
                return ['list' => []];
            }

            $doc = $this->html($rsp);
            $videos = $this->getVideos($doc, null, $typeId);

            return [
                'list' => $videos,
                'page' => intval($pg),
                'pagecount' => 999,
                'limit' => 20,
                'total' => 19980
            ];
        } catch (Exception $e) {
            return ['list' => []];
        }
    }

    public function getSearchContent($key, $pg = 1) {
        try {
            $encodedKey = urlencode($key);
            $searchUrl = "{$this->host}/index.php/vod/type/id/1/wd/{$encodedKey}/page/{$pg}.html";
            
            $rsp = $this->fetch($searchUrl);
            if (!$rsp) {
                return ['list' => []];
            }

            $doc = $this->html($rsp);
            $videos = $this->getVideos($doc, 20);

            // 尝试解析分页信息
            $pagecount = 5;
            $total = 100;

            return [
                'list' => $videos,
                'page' => intval($pg),
                'pagecount' => $pagecount,
                'limit' => 20,
                'total' => $total
            ];
        } catch (Exception $e) {
            return ['list' => []];
        }
    }

    public function getDetailContent($ids) {
        $vid = $ids[0];
        
        try {
            // 检查特殊分区链接
            if (strpos($vid, 'special_') === 0) {
                $parts = explode('_', $vid);
                if (count($parts) >= 4) {
                    $categoryId = $parts[1];
                    $videoId = $parts[2];
                    $encodedUrl = implode('_', array_slice($parts, 3));
                    $playUrl = urldecode($encodedUrl);
                    
                    // 解析播放链接参数
                    $parsedUrl = parse_url($playUrl);
                    parse_str($parsedUrl['query'] ?? '', $queryParams);
                    
                    $videoUrl = $queryParams['v'] ?? '';
                    $picUrl = $queryParams['b'] ?? '';
                    $titleEncrypted = $queryParams['m'] ?? '';
                    
                    $title = $this->decryptTitle($titleEncrypted);
                    
                    return [
                        'list' => [[
                            'vod_id' => $vid,
                            'vod_name' => $title,
                            'vod_pic' => $picUrl,
                            'vod_remarks' => '',
                            'vod_year' => '',
                            'vod_play_from' => '直接播放',
                            'vod_play_url' => "第1集\${$playUrl}"
                        ]]
                    ];
                }
            }

            // 常规处理
            if (substr_count($vid, '_') >= 2) {
                list($domain, $categoryId, $videoId) = explode('_', $vid);
            } else {
                list($domain, $videoId) = explode('_', $vid);
            }

            $detailUrl = "https://{$domain}/index.php/vod/detail/id/{$videoId}.html";
            $rsp = $this->fetch($detailUrl);
            
            if (!$rsp) {
                return ['list' => []];
            }

            $doc = $this->html($rsp);
            $videoInfo = $this->getDetail($doc, $rsp, $vid);
            
            return $videoInfo ? ['list' => [$videoInfo]] : ['list' => []];
        } catch (Exception $e) {
            return ['list' => []];
        }
    }

    public function getPlayerContent($id) {
        try {
            // 检查特殊分区链接
            if (strpos($id, 'special_') === 0) {
                $parts = explode('_', $id);
                if (count($parts) >= 4) {
                    $categoryId = $parts[1];
                    $videoId = $parts[2];
                    $encodedUrl = implode('_', array_slice($parts, 3));
                    $playUrl = urldecode($encodedUrl);
                    
                    $parsedUrl = parse_url($playUrl);
                    parse_str($parsedUrl['query'] ?? '', $queryParams);
                    $videoUrl = $queryParams['v'] ?? '';
                    
                    if ($videoUrl) {
                        if (strpos($videoUrl, '//') === 0) {
                            $videoUrl = 'https:' . $videoUrl;
                        } elseif (strpos($videoUrl, 'http') !== 0) {
                            $videoUrl = $this->host . '/' . ltrim($videoUrl, '/');
                        }
                        
                        return ['parse' => 0, 'playUrl' => '', 'url' => $videoUrl];
                    }
                }
            }

            // 检查完整URL
            if (strpos($id, 'http') === 0) {
                $parsedUrl = parse_url($id);
                parse_str($parsedUrl['query'] ?? '', $queryParams);
                
                $videoUrl = $queryParams['v'] ?? '';
                if (!$videoUrl) {
                    foreach ($queryParams as $key => $value) {
                        if (in_array($key, ['url', 'src', 'file'])) {
                            $videoUrl = $value;
                            break;
                        }
                    }
                }
                
                if ($videoUrl) {
                    $videoUrl = urldecode($videoUrl);
                    if (strpos($videoUrl, '//') === 0) {
                        $videoUrl = 'https:' . $videoUrl;
                    } elseif (strpos($videoUrl, 'http') !== 0) {
                        $videoUrl = $this->host . '/' . ltrim($videoUrl, '/');
                    }
                    
                    return ['parse' => 0, 'playUrl' => '', 'url' => $videoUrl];
                } else {
                    // 从页面提取视频链接
                    $rsp = $this->fetch($id);
                    if ($rsp) {
                        $videoUrl = $this->extractDirectVideoUrl($rsp);
                        if ($videoUrl) {
                            return ['parse' => 0, 'playUrl' => '', 'url' => $videoUrl];
                        }
                    }
                    
                    return ['parse' => 1, 'playUrl' => '', 'url' => $id];
                }
            }

            // 提取视频ID和分类ID
            $parts = explode('_', $id);
            $videoId = end($parts);
            $categoryId = count($parts) >= 3 ? $parts[1] : '';
            
            // 特殊分类处理
            if ($categoryId && in_array($categoryId, $this->specialCategories)) {
                $playPageUrl = "{$this->host}/index.php/vod/play/id/{$videoId}.html";
                $rsp = $this->fetch($playPageUrl);
                
                if ($rsp) {
                    $videoUrl = $this->extractDirectVideoUrl($rsp);
                    if ($videoUrl) {
                        return ['parse' => 0, 'playUrl' => '', 'url' => $videoUrl];
                    }
                }
                
                return $this->getVideoByApi($id, $videoId);
            } else {
                return $this->getVideoByApi($id, $videoId);
            }
        } catch (Exception $e) {
            $playUrl = strpos($id, '_') !== false ? 
                "https://618041.xyz/html/kkyd.html?m=" . explode('_', $id)[1] : 
                "{$this->host}/html/kkyd.html?m={$id}";
                
            return ['parse' => 1, 'playUrl' => '', 'url' => $playUrl];
        }
    }

    private function getVideoByApi($id, $videoId) {
        try {
            $apiUrl = "{$this->apiHost}/api/v2/vod/reqplay/{$videoId}";
            $apiHeaders = [
                'Referer: ' . $this->host . '/',
                'Origin: ' . $this->host,
                'X-Requested-With: XMLHttpRequest'
            ];
            
            $apiResponse = $this->fetch($apiUrl, $apiHeaders);
            if ($apiResponse) {
                $data = json_decode($apiResponse, true);
                
                if ($data && isset($data['retcode'])) {
                    if ($data['retcode'] == 3) {
                        $videoUrl = $data['data']['httpurl_preview'] ?? '';
                    } else {
                        $videoUrl = $data['data']['httpurl'] ?? '';
                    }
                    
                    if ($videoUrl) {
                        $videoUrl = str_replace('?300', '', $videoUrl);
                        return ['parse' => 0, 'playUrl' => '', 'url' => $videoUrl];
                    }
                }
            }
            
            $playUrl = strpos($id, '_') !== false ? 
                "https://618041.xyz/html/kkyd.html?m=" . explode('_', $id)[1] : 
                "{$this->host}/html/kkyd.html?m={$id}";
                
            return ['parse' => 1, 'playUrl' => '', 'url' => $playUrl];
        } catch (Exception $e) {
            $playUrl = strpos($id, '_') !== false ? 
                "https://618041.xyz/html/kkyd.html?m=" . explode('_', $id)[1] : 
                "{$this->host}/html/kkyd.html?m={$id}";
                
            return ['parse' => 1, 'playUrl' => '', 'url' => $playUrl];
        }
    }

    private function extractDirectVideoUrl($htmlContent) {
        $patterns = [
            '/v=([^&]+\.(?:m3u8|mp4))/',
            '/"url"\s*:\s*["\']([^"\']+\.(?:mp4|m3u8))["\']/',
            '/src\s*=\s*["\']([^"\']+\.(?:mp4|m3u8))["\']/',
            '/http[^\s<>"\'?]+\.(?:mp4|m3u8)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $htmlContent, $matches)) {
                foreach ($matches[1] ?? $matches[0] as $match) {
                    $extractedUrl = str_replace('\\', '', $match);
                    $extractedUrl = urldecode($extractedUrl);
                    
                    if (strpos($extractedUrl, '//') === 0) {
                        $extractedUrl = 'https:' . $extractedUrl;
                    } elseif (strpos($extractedUrl, 'http') === 0) {
                        return $extractedUrl;
                    }
                }
            }
        }
        
        return null;
    }

    private function getVideos($doc, $limit = null, $categoryId = null) {
        $videos = [];
        
        $elements = $doc->query('//a[@class="vodbox"]');
        if (!$elements) {
            return $videos;
        }
        
        foreach ($elements as $element) {
            $video = $this->extractVideo($element, $categoryId);
            if ($video) {
                $videos[] = $video;
            }
        }
        
        return $limit ? array_slice($videos, 0, $limit) : $videos;
    }

    private function extractVideo($element, $categoryId = null) {
        try {
            $link = $element->getAttribute('href');
            if (strpos($link, '/') === 0) {
                $link = $this->host . $link;
            }
            
            $isSpecialLink = strpos($link, 'ar-kk.html') !== false || strpos($link, 'ar.html') !== false;
            
            if ($isSpecialLink && $categoryId && in_array($categoryId, $this->specialCategories)) {
                $parsedUrl = parse_url($link);
                parse_str($parsedUrl['query'] ?? '', $queryParams);
                
                $videoUrl = $queryParams['v'] ?? '';
                if ($videoUrl) {
                    if (preg_match('/\/([a-f0-9-]+)\/video\.m3u8/', $videoUrl, $matches)) {
                        $videoId = $matches[1];
                    } else {
                        $videoId = abs(crc32($link)) % 1000000;
                    }
                } else {
                    $videoId = abs(crc32($link)) % 1000000;
                }
                
                $finalVodId = "special_{$categoryId}_{$videoId}_" . urlencode($link);
            } else {
                $vodId = $this->regStr('/m=(\d+)/', $link);
                if (!$vodId) {
                    $vodId = abs(crc32($link)) % 1000000;
                }
                
                $finalVodId = "618041.xyz_{$vodId}";
                if ($categoryId) {
                    $finalVodId = "618041.xyz_{$categoryId}_{$vodId}";
                }
            }
            
            // 提取标题
            $title = '';
            $titlePaths = [
                './/p[@class="km-script"]/text()',
                './/p[contains(@class, "script")]/text()',
                './/p/text()',
                './/h3/text()',
                './/h4/text()'
            ];
            
            foreach ($titlePaths as $path) {
                $titleNodes = $element->ownerDocument->saveXML($element);
                if (preg_match('/<[^>]*class="[^"]*km-script[^"]*"[^>]*>([^<]*)<\/[^>]*>/', $titleNodes, $matches)) {
                    $title = trim($matches[1]);
                    break;
                }
            }
            
            if (!$title) {
                return null;
            }
            
            $title = $this->decryptTitle($title);
            
            // 提取图片
            $pic = '';
            $imgPaths = [
                './/img/@data-original',
                './/img/@src'
            ];
            
            foreach ($imgPaths as $path) {
                $imgNodes = $element->ownerDocument->saveXML($element);
                if (preg_match('/<img[^>]*(?:data-original|src)="([^"]*)"/', $imgNodes, $matches)) {
                    $pic = $matches[1];
                    break;
                }
            }
            
            if ($pic) {
                if (strpos($pic, '//') === 0) {
                    $pic = 'https:' . $pic;
                } elseif (strpos($pic, '/') === 0) {
                    $pic = $this->host . $pic;
                }
            }
            
            return [
                'vod_id' => $finalVodId,
                'vod_name' => $title,
                'vod_pic' => $pic,
                'vod_remarks' => '',
                'vod_year' => ''
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function decryptTitle($encryptedText) {
        $decryptedChars = [];
        for ($i = 0; $i < strlen($encryptedText); $i++) {
            $codePoint = ord($encryptedText[$i]);
            $decryptedCode = $codePoint ^ 128;
            $decryptedChars[] = chr($decryptedCode);
        }
        return implode('', $decryptedChars);
    }

    private function getDetail($doc, $htmlContent, $vid) {
        try {
            $title = $this->getText($doc, [
                '//h1/text()',
                '//title/text()'
            ]);
            
            $pic = $this->getText($doc, [
                '//div[contains(@class,"dyimg")]//img/@src',
                '//img[contains(@class,"poster")]/@src'
            ]);
            
            if ($pic && strpos($pic, '/') === 0) {
                $pic = $this->host . $pic;
            }
            
            $desc = $this->getText($doc, [
                '//div[contains(@class,"yp_context")]/text()',
                '//div[contains(@class,"introduction")]//text()'
            ]);
            
            $actor = $this->getText($doc, [
                '//span[contains(text(),"主演")]/following-sibling::*/text()'
            ]);
            
            $director = $this->getText($doc, [
                '//span[contains(text(),"导演")]/following-sibling::*/text()'
            ]);

            $playFrom = [];
            $playUrls = [];
            
            // 查找播放链接
            $playerLinkPatterns = [
                '/href="(.*?ar\.html.*?)"/',
                '/href="(.*?kkyd\.html.*?)"/',
                '/href="(.*?ar-kk\.html.*?)"/'
            ];
            
            $playerLinks = [];
            foreach ($playerLinkPatterns as $pattern) {
                if (preg_match_all($pattern, $htmlContent, $matches)) {
                    $playerLinks = array_merge($playerLinks, $matches[1]);
                }
            }
            
            if (!empty($playerLinks)) {
                $episodes = [];
                foreach ($playerLinks as $link) {
                    $fullUrl = $this->resolveUrl($link);
                    $episodes[] = "第1集\${$fullUrl}";
                }
                
                if (!empty($episodes)) {
                    $playFrom[] = "默认播放源";
                    $playUrls[] = implode('#', $episodes);
                }
            }
            
            if (empty($playFrom)) {
                return [
                    'vod_id' => $vid,
                    'vod_name' => $title,
                    'vod_pic' => $pic,
                    'type_name' => '',
                    'vod_year' => '',
                    'vod_area' => '',
                    'vod_remarks' => '',
                    'vod_actor' => $actor,
                    'vod_director' => $director,
                    'vod_content' => $desc,
                    'vod_play_from' => '默认播放源',
                    'vod_play_url' => "第1集\${$vid}"
                ];
            }

            return [
                'vod_id' => $vid,
                'vod_name' => $title,
                'vod_pic' => $pic,
                'type_name' => '',
                'vod_year' => '',
                'vod_area' => '',
                'vod_remarks' => '',
                'vod_actor' => $actor,
                'vod_director' => $director,
                'vod_content' => $desc,
                'vod_play_from' => implode('$$$', $playFrom),
                'vod_play_url' => implode('$$$', $playUrls)
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    private function getText($doc, $selectors) {
        foreach ($selectors as $selector) {
            $nodes = $doc->query($selector);
            if ($nodes && $nodes->length > 0) {
                for ($i = 0; $i < $nodes->length; $i++) {
                    $text = trim($nodes->item($i)->nodeValue);
                    if ($text) {
                        return $text;
                    }
                }
            }
        }
        return '';
    }

    private function resolveUrl($url) {
        if (strpos($url, 'http') === 0) {
            return $url;
        } elseif (strpos($url, '//') === 0) {
            return 'https:' . $url;
        } else {
            return $this->host . '/' . ltrim($url, '/');
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
    $spider = new AppleVideoSpider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 */
function getCategory($tid, $page, $extend) {
    $spider = new AppleVideoSpider();
    return $spider->getCategoryContent($tid, $page, $extend);
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $spider = new AppleVideoSpider();
    return $spider->getDetailContent(explode(',', $ids));
}

/**
 * 搜索
 */
function search($keyword, $page) {
    $spider = new AppleVideoSpider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 */
function getPlay($flag, $id) {
    $spider = new AppleVideoSpider();
    return $spider->getPlayerContent($id);
}
?>