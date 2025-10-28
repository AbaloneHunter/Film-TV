<?php
/**
 * TVBox PHP 爬虫脚本示例 - 金牌影视版
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
        $extend = $_GET['extend'] ?? '{}';
        echo json_encode(getCategory($tid, $page, json_decode($extend, true)));
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

class JinPaiSpider {
    private $host = "https://www.hkybqufgh.com";
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'application/json, text/plain, */*',
        'Origin' => 'https://www.hkybqufgh.com',
        'Referer' => 'https://www.hkybqufgh.com/',
        'DNT' => '1'
    ];
    private $session;
    private $key = 'cb808529bae6b6be45ecfab29a4889bc';

    public function __construct($extend = '') {
        if ($extend && isset(json_decode($extend, true)['site'])) {
            $hosts = json_decode($extend, true)['site'];
            $this->host = $this->getFastestHost($hosts);
        }
        $this->session = $this->createSession();
    }

    private function createSession() {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        return $ch;
    }

    private function getFastestHost($hosts) {
        if (is_string($hosts)) {
            $hosts = array_map('trim', explode(',', $hosts));
        }
        
        if (empty($hosts)) {
            return $this->host;
        }
        
        if (count($hosts) === 1) {
            return $hosts[0];
        }

        $results = [];
        $mh = curl_multi_init();
        $handles = [];

        foreach ($hosts as $host) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $host,
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => false
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$host] = $ch;
        }

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        foreach ($handles as $host => $ch) {
            $info = curl_getinfo($ch);
            if ($info['http_code'] === 200) {
                $results[$host] = $info['total_time'];
            } else {
                $results[$host] = PHP_FLOAT_MAX;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);

        if (empty($results)) {
            return $hosts[0];
        }

        return array_search(min($results), $results);
    }

    private function buildSign($params) {
        $params['key'] = $this->key;
        $params['t'] = round(microtime(true) * 1000);
        
        $paramStr = $this->buildParamString($params);
        $md5 = md5($paramStr);
        $sign = sha1($md5);
        
        return [
            'sign' => $sign,
            't' => $params['t'],
            'deviceid' => $this->generateDeviceId()
        ];
    }

    private function buildParamString($params) {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    private function generateDeviceId() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function fetch($url, $params = []) {
        $signData = $this->buildSign($params);
        
        $headers = $this->headers;
        $headers['sign'] = $signData['sign'];
        $headers['t'] = $signData['t'];
        $headers['deviceid'] = $signData['deviceid'];
        
        curl_setopt_array($this->session, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $this->buildHeaders($headers)
        ]);
        
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    private function buildHeaders($headers) {
        $headerArray = [];
        foreach ($headers as $key => $value) {
            $headerArray[] = $key . ': ' . $value;
        }
        return $headerArray;
    }

    public function getHomeContent() {
        // 获取分类数据
        $categoryData = json_decode($this->fetch($this->host . "/api/mw-movie/anonymous/get/filer/type"), true);
        // 获取筛选数据
        $filterData = json_decode($this->fetch($this->host . "/api/mw-movie/anonymous/v1/get/filer/list"), true);
        // 获取首页视频数据
        $homeData = json_decode($this->fetch($this->host . "/api/mw-movie/anonymous/v1/home/all/list"), true);
        // 获取热门搜索数据
        $hotData = json_decode($this->fetch($this->host . "/api/mw-movie/anonymous/home/hotSearch"), true);

        $result = [
            "class" => [],
            "filters" => [],
            "list" => []
        ];

        // 处理分类
        if (isset($categoryData['data'])) {
            foreach ($categoryData['data'] as $category) {
                $result['class'][] = [
                    'type_id' => strval($category['typeId']),
                    'type_name' => $category['typeName']
                ];
            }
        }

        // 处理筛选条件
        if (isset($filterData['data'])) {
            $sortValues = [
                ["n" => "最近更新", "v" => "2"],
                ["n" => "人气高低", "v" => "3"], 
                ["n" => "评分高低", "v" => "4"]
            ];
            
            foreach ($filterData['data'] as $tid => $data) {
                $currentSortValues = $sortValues;
                if ($tid == '1') {
                    array_shift($currentSortValues);
                }
                
                $filters = [
                    [
                        "key" => "type",
                        "name" => "类型",
                        "value" => array_map(function($item) {
                            return ["n" => $item["itemText"], "v" => $item["itemValue"]];
                        }, $data["typeList"])
                    ]
                ];
                
                if (!empty($data["plotList"])) {
                    $filters[] = [
                        "key" => "v_class",
                        "name" => "剧情",
                        "value" => array_map(function($item) {
                            return ["n" => $item["itemText"], "v" => $item["itemText"]];
                        }, $data["plotList"])
                    ];
                }
                
                $filters[] = [
                    "key" => "area", 
                    "name" => "地区",
                    "value" => array_map(function($item) {
                        return ["n" => $item["itemText"], "v" => $item["itemText"]];
                    }, $data["districtList"])
                ];
                
                $filters[] = [
                    "key" => "year",
                    "name" => "年份", 
                    "value" => array_map(function($item) {
                        return ["n" => $item["itemText"], "v" => $item["itemText"]];
                    }, $data["yearList"])
                ];
                
                $filters[] = [
                    "key" => "lang",
                    "name" => "语言",
                    "value" => array_map(function($item) {
                        return ["n" => $item["itemText"], "v" => $item["itemText"]];
                    }, $data["languageList"])
                ];
                
                $filters[] = [
                    "key" => "sort",
                    "name" => "排序", 
                    "value" => $currentSortValues
                ];
                
                $result['filters'][$tid] = $filters;
            }
        }

        // 处理视频列表
        $videos = [];
        if (isset($homeData['data'])) {
            foreach ($homeData['data'] as $categoryVideos) {
                if (isset($categoryVideos['list'])) {
                    foreach ($categoryVideos['list'] as $video) {
                        $videos[] = $this->convertVideoFields($video);
                    }
                }
            }
        }
        
        if (isset($hotData['data'])) {
            foreach ($hotData['data'] as $video) {
                $videos[] = $this->convertVideoFields($video);
            }
        }
        
        $result['list'] = $videos;

        return $result;
    }

    public function getCategoryContent($tid, $pg, $extend) {
        $params = [
            "area" => $extend['area'] ?? '',
            "filterStatus" => "1",
            "lang" => $extend['lang'] ?? '',
            "pageNum" => $pg,
            "pageSize" => "30",
            "sort" => $extend['sort'] ?? '1',
            "sortBy" => "1",
            "type" => $extend['type'] ?? '',
            "type1" => $tid,
            "v_class" => $extend['v_class'] ?? '',
            "year" => $extend['year'] ?? ''
        ];

        $url = $this->host . "/api/mw-movie/anonymous/video/list?" . $this->buildParamString($params);
        $response = json_decode($this->fetch($url, $params), true);

        $result = [
            "list" => [],
            "page" => intval($pg),
            "pagecount" => 9999,
            "limit" => 90,
            "total" => 999999
        ];

        if (isset($response['data']['list'])) {
            foreach ($response['data']['list'] as $video) {
                $result['list'][] = $this->convertVideoFields($video);
            }
        }

        return $result;
    }

    public function getSearchContent($key, $pg = 1) {
        $params = [
            "keyword" => $key,
            "pageNum" => $pg,
            "pageSize" => "8",
            "sourceCode" => "1"
        ];

        $url = $this->host . "/api/mw-movie/anonymous/video/searchByWord?" . $this->buildParamString($params);
        $response = json_decode($this->fetch($url, $params), true);

        $result = ["list" => []];

        if (isset($response['data']['result']['list'])) {
            foreach ($response['data']['result']['list'] as $video) {
                $result['list'][] = $this->convertVideoFields($video);
            }
        }

        $result['page'] = $pg;
        return $result;
    }

    public function getDetailContent($ids) {
        $params = ['id' => $ids[0]];
        $url = $this->host . "/api/mw-movie/anonymous/video/detail?id=" . $ids[0];
        $response = json_decode($this->fetch($url, $params), true);

        $result = ["list" => []];

        if (isset($response['data'])) {
            $video = $this->convertVideoFields($response['data']);
            
            // 处理播放列表
            if (isset($video['episodelist']) && is_array($video['episodelist'])) {
                $playUrls = [];
                foreach ($video['episodelist'] as $episode) {
                    $episodeName = count($video['episodelist']) > 1 ? 
                        $episode['name'] : $video['vod_name'];
                    $playUrls[] = $episodeName . '$' . $ids[0] . '@@' . $episode['nid'];
                }
                
                $video['vod_play_from'] = '金牌影视';
                $video['vod_play_url'] = implode('#', $playUrls);
                unset($video['episodelist']);
            }
            
            $result['list'][] = $video;
        }

        return $result;
    }

    public function getPlayerContent($id) {
        $ids = explode('@@', $id);
        if (count($ids) < 2) {
            return ["parse" => 1, "playUrl" => "", "url" => ""];
        }

        $params = [
            'clientType' => '1',
            'id' => $ids[0],
            'nid' => $ids[1]
        ];

        $url = $this->host . "/api/mw-movie/anonymous/v2/video/episode/url?clientType=1&id=" . $ids[0] . "&nid=" . $ids[1];
        $response = json_decode($this->fetch($url, $params), true);

        $result = ["parse" => 0, "playUrl" => "", "url" => ""];
        $vlist = [];

        if (isset($response['data']['list'])) {
            foreach ($response['data']['list'] as $quality) {
                $vlist[] = $quality['resolutionName'];
                $vlist[] = $quality['url'];
            }
            $result['url'] = $vlist;
        }

        return $result;
    }

    private function convertVideoFields($video) {
        $converted = [];
        foreach ($video as $key => $value) {
            $newKey = $this->convertFieldName($key);
            $converted[$newKey] = $value;
        }
        return $converted;
    }

    private function convertFieldName($field) {
        $field = strtolower($field);
        if (strpos($field, 'vod') === 0 && strlen($field) > 3) {
            $field = 'vod_' . substr($field, 3);
        }
        if (strpos($field, 'type') === 0 && strlen($field) > 4) {
            $field = 'type_' . substr($field, 4);
        }
        return $field;
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
    $spider = new JinPaiSpider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 * @param string $tid 分类ID
 * @param string $page 页码
 * @param array $extend 扩展参数
 */
function getCategory($tid, $page, $extend = []) {
    $spider = new JinPaiSpider();
    return $spider->getCategoryContent($tid, $page, $extend);
}

/**
 * 视频详情
 * @param string $ids 视频ID（多个用逗号分隔）
 */
function getDetail($ids) {
    $spider = new JinPaiSpider();
    return $spider->getDetailContent(explode(',', $ids));
}

/**
 * 搜索
 * @param string $keyword 关键词
 * @param string $page 页码
 */
function search($keyword, $page) {
    $spider = new JinPaiSpider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 * @param string $flag 线路标识
 * @param string $id 播放地址ID
 */
function getPlay($flag, $id) {
    $spider = new JinPaiSpider();
    return $spider->getPlayerContent($id);
}
?>