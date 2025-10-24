<?php
/**
 * TVBox PHP 爬虫脚本示例 - 永乐视频版
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
        echo json_encode(getCategory($tid, $page));
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

class YongLeSpider {
    private $host = "https://www.ylys.tv/";
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer' => 'https://www.ylys.tv/'
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
            CURLOPT_HTTPHEADER => $this->buildHeaders($this->headers)
        ]);
        return $ch;
    }

    private function buildHeaders($headers) {
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = $key . ': ' . $value;
        }
        return $headerArray;
    }

    public function fetch($url) {
        curl_setopt($this->session, CURLOPT_URL, $url);
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        $contentType = curl_getinfo($this->session, CURLINFO_CONTENT_TYPE);
        $encoding = 'UTF-8';
        if (strpos($contentType, 'ISO-8859-1') !== false) {
            $encoding = 'UTF-8';
        }
        
        if ($encoding !== 'UTF-8') {
            $response = mb_convert_encoding($response, 'UTF-8', $encoding);
        }
        
        return $response;
    }

    public function getHomeContent() {
        $result = [
            "class" => [
                ['type_id' => '1', 'type_name' => '电影'],
                ['type_id' => '2', 'type_name' => '剧集'],
                ['type_id' => '3', 'type_name' => '综艺'],
                ['type_id' => '4', 'type_name' => '动漫']
            ],
            "filters" => $this->getFilters(),
            "list" => []
        ];

        $rsp = $this->fetch($this->host);
        if ($rsp) {
            $result['list'] = $this->extractVideos($rsp, 20);
        }

        return $result;
    }

    public function getCategoryContent($tid, $pg) {
        $result = [
            "list" => [],
            "page" => intval($pg),
            "pagecount" => 99,
            "limit" => 20,
            "total" => 1980
        ];

        $url = $pg > 1 ? 
            $this->host . "vodtype/{$tid}/page/{$pg}/" : 
            $this->host . "vodtype/{$tid}/";
            
        $rsp = $this->fetch($url);
        if ($rsp) {
            $result['list'] = $this->extractVideos($rsp);
        }

        return $result;
    }

    public function getSearchContent($key, $pg = 1) {
        $result = ["list" => []];
        $searchKey = urlencode($key);
        
        $url = $pg > 1 ? 
            $this->host . "vodsearch/{$searchKey}-------------/page/{$pg}/" : 
            $this->host . "vodsearch/{$searchKey}-------------/";
            
        $rsp = $this->fetch($url);
        if ($rsp) {
            $result['list'] = $this->extractSearchResults($rsp);
        }

        return $result;
    }

    public function getDetailContent($ids) {
        $result = ["list" => []];
        $vid = $ids[0];
        
        $rsp = $this->fetch($this->host . "voddetail/{$vid}/");
        if (!$rsp) {
            return $result;
        }

        list($playFrom, $playUrl) = $this->extractPlayInfo($rsp, $vid);
        
        if (!empty($playFrom)) {
            $result['list'][] = [
                'vod_id' => $vid,
                'vod_name' => $this->extractTitle($rsp),
                'vod_pic' => $this->extractPic($rsp),
                'vod_content' => $this->extractDesc($rsp),
                'vod_remarks' => $this->extractRemarks($rsp),
                'vod_play_from' => implode('$$$', $playFrom),
                'vod_play_url' => implode('$$$', $playUrl)
            ];
        }

        return $result;
    }

    public function getPlayerContent($id) {
        $result = ["parse" => 1, "playUrl" => "", "url" => ""];
        
        if (strpos($id, '-') === false) {
            return $result;
        }

        $rsp = $this->fetch($this->host . "play/{$id}/");
        if (!$rsp) {
            return $result;
        }

        if (preg_match('/var player_aaaa=.*?"url":"([^"]+\.m3u8)"/is', $rsp, $matches)) {
            $realUrl = str_replace(['\u002F', '\/'], '/', $matches[1]);
            $result["parse"] = 0;
            $result["url"] = $realUrl;
        } else {
            $result["url"] = $this->host . "play/{$id}/";
        }

        return $result;
    }

    private function getFilters() {
        return [
            "1" => [[
                "key" => "class",
                "name" => "类型",
                "value" => [
                    ["n" => "全部", "v" => ""],
                    ["n" => "动作片", "v" => "6"],
                    ["n" => "喜剧片", "v" => "7"],
                    ["n" => "爱情片", "v" => "8"],
                    ["n" => "科幻片", "v" => "9"],
                    ["n" => "恐怖片", "v" => "11"]
                ]
            ]],
            "2" => [[
                "key" => "class",
                "name" => "类型",
                "value" => [
                    ["n" => "全部", "v" => ""],
                    ["n" => "国产剧", "v" => "13"],
                    ["n" => "港台剧", "v" => "14"],
                    ["n" => "日剧", "v" => "15"],
                    ["n" => "韩剧", "v" => "33"],
                    ["n" => "欧美剧", "v" => "16"]
                ]
            ]],
            "3" => [[
                "key" => "class",
                "name" => "类型",
                "value" => [
                    ["n" => "全部", "v" => ""],
                    ["n" => "内地综艺", "v" => "27"],
                    ["n" => "港台综艺", "v" => "28"],
                    ["n" => "日本综艺", "v" => "29"],
                    ["n" => "韩国综艺", "v" => "36"]
                ]
            ]],
            "4" => [[
                "key" => "class",
                "name" => "类型",
                "value" => [
                    ["n" => "全部", "v" => ""],
                    ["n" => "国产动漫", "v" => "31"],
                    ["n" => "日本动漫", "v" => "32"],
                    ["n" => "欧美动漫", "v" => "42"],
                    ["n" => "其他动漫", "v" => "43"]
                ]
            ]]
        ];
    }

    private function extractVideos($html, $limit = 0) {
        $videos = [];
        $pattern = '/<a href="\/voddetail\/(\d+)\/".*?title="([^"]+)".*?<div class="module-item-note">([^<]+)<\/div>.*?data-original="([^"]+)"/is';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $vid = trim($match[1]);
                $title = trim($match[2]);
                $remark = trim($match[3]);
                $pic = trim($match[4]);
                
                if (strpos($pic, '/') === 0) {
                    $pic = $this->host . ltrim($pic, '/');
                }
                
                $videos[] = [
                    'vod_id' => $vid,
                    'vod_name' => $title,
                    'vod_pic' => $pic,
                    'vod_remarks' => $remark
                ];
            }
        }
        
        if ($limit > 0 && count($videos) > $limit) {
            $videos = array_slice($videos, 0, $limit);
        }
        
        return $videos;
    }

    private function extractSearchResults($html) {
        $videos = [];
        
        $pattern = '/<a href="\/voddetail\/(\d+)\/".*?title="([^"]+)".*?data-original="([^"]+)".*?<div class="module-item-note">([^<]*)<\/div>/is';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $vid = trim($match[1]);
                $title = trim($match[2]);
                $pic = trim($match[3]);
                $remark = trim($match[4]);
                
                if (strpos($pic, '/') === 0) {
                    $pic = $this->host . ltrim($pic, '/');
                }
                
                $videos[] = [
                    'vod_id' => $vid,
                    'vod_name' => $title,
                    'vod_pic' => $pic,
                    'vod_remarks' => $remark
                ];
            }
        }
        
        return $videos;
    }

    private function extractPlayInfo($html, $vid) {
        $playFrom = [];
        $playUrl = [];
        
        $pattern = '/<(?:div|a)[^>]*class="[^"]*module-tab-item[^"]*"[^>]*>(?:.*?<span>([^<]+)<\/span>.*?<small>(\d+)<\/small>|.*?<span>([^<]+)<\/span>.*?<small class="no">(\d+)<\/small>)<\/(?:div|a)>/is';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lineName = !empty($match[1]) ? $match[1] : $match[3];
                
                if (in_array($lineName, $playFrom)) {
                    continue;
                }
                
                $playFrom[] = $lineName;
                $lineId = $this->getLineId($html, $vid, $lineName);
                
                $epPattern = '/<a class="module-play-list-link" href="\/play\/' . $vid . '-' . $lineId . '-(\d+)\/"[^>]*>.*?<span>([^<]+)<\/span><\/a>/is';
                $eps = [];
                
                if (preg_match_all($epPattern, $html, $epMatches, PREG_SET_ORDER)) {
                    foreach ($epMatches as $epMatch) {
                        $eps[] = trim($epMatch[2]) . '$' . $vid . '-' . $lineId . '-' . trim($epMatch[1]);
                    }
                }
                
                $playUrl[] = implode('#', $eps);
            }
        }
        
        return [$playFrom, $playUrl];
    }

    private function getLineId($html, $vid, $lineName) {
        $pattern = '/<a[^>]*href="\/play\/' . $vid . '-(\d+)-1\/"[^>]*>.*?<span>' . preg_quote($lineName, '/') . '<\/span>/is';
        
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        
        $lineIdMap = [
            "全球3线" => "3",
            "大陆0线" => "1", 
            "大陆3线" => "4",
            "大陆5线" => "2",
            "大陆6线" => "3"
        ];
        
        return $lineIdMap[$lineName] ?? "1";
    }

    private function extractTitle($html) {
        if (preg_match('/<meta property="og:title" content="([^"]+)-[^-]+$"/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return "";
    }

    private function extractPic($html) {
        if (preg_match('/<meta property="og:image" content="([^"]+)"/is', $html, $matches)) {
            $pic = trim($matches[1]);
            if ($pic && strpos($pic, '/') === 0) {
                return $this->host . ltrim($pic, '/');
            }
            return $pic;
        }
        return "";
    }

    private function extractDesc($html) {
        if (preg_match('/<meta property="og:description" content="([^"]+)"/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return "暂无简介";
    }

    private function extractRemarks($html) {
        $year = "未知年份";
        if (preg_match('/<a title="(\d+)" href="\/vodshow\/\d+-----------\1\/">/is', $html, $matches)) {
            $year = $matches[1];
        }
        
        $area = "未知产地";
        if (preg_match('/<a title="([^"]+)" href="\/vodshow\/\d+-%E5%A2%A8%E8%A5%BF%E5%93%A5----------\/">/is', $html, $matches)) {
            $area = $matches[1];
        }
        
        $typeStr = "未知类型";
        if (preg_match('/vod_class":"([^"]+)"/is', $html, $matches)) {
            $typeStr = str_replace(',', '/', $matches[1]);
        }
        
        return $year . " | " . $area . " | " . $typeStr;
    }

    public function __destruct() {
        if ($this->session) {
            curl_close($this->session);
        }
    }
}

/**
 * 首页数据
 * 返回分类列表和推荐内容
 */
function getHome() {
    $spider = new YongLeSpider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 * @param string $tid 分类ID
 * @param string $page 页码
 */
function getCategory($tid, $page) {
    $spider = new YongLeSpider();
    return $spider->getCategoryContent($tid, $page);
}

/**
 * 视频详情
 * @param string $ids 视频ID（多个用逗号分隔）
 */
function getDetail($ids) {
    $spider = new YongLeSpider();
    return $spider->getDetailContent(explode(',', $ids));
}

/**
 * 搜索
 * @param string $keyword 关键词
 * @param string $page 页码
 */
function search($keyword, $page) {
    $spider = new YongLeSpider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 * @param string $flag 线路标识
 * @param string $id 播放地址ID
 */
function getPlay($flag, $id) {
    $spider = new YongLeSpider();
    return $spider->getPlayerContent($id);
}
?>