<?php
/**
 * 永乐视频爬虫脚本 - 兼容Apple CMS API格式
 * 支持操作：detail（首页/分类/详情）、search、play
 * 支持二级分类和自定义样式
 *
 * 二级分类实现：
 * 1. 当JSON包含'is_sub' => true时，表示返回二级分类列表（显示为文件夹）
 * 2. 点击二级分类时，筛选参数f中包含'is_sub' => 'true'
 * 3. 根据is_sub判断返回二级分类还是实际内容
 *
 * 自定义样式：
 * 1. style字段位于JSON最外层，应用于所有列表项
 * 2. type: 'rect'(矩形), 'oval'(椭圆), 'round'(圆形)
 * 3. ratio: 宽高比例，如1.5表示3:2，0.67表示2:3
 *
 * @package YongLeSpider
 * @version 1.0.0
 */

namespace YongLeSpider;

use Exception;

// 设置响应头为JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * 配置类，存储常量和设置
 */
class Config {
    public const HOST = 'https://www.ylys.tv/';
    public const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer' => 'https://www.ylys.tv/'
    ];
}

/**
 * HTTP客户端接口
 */
interface HttpClientInterface {
    public function fetch(string $url): ?string;
}

/**
 * cURL实现的HTTP客户端
 */
class CurlHttpClient implements HttpClientInterface {
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
            CURLOPT_HTTPHEADER => $this->buildHeaders(Config::HEADERS)
        ]);
        return $ch;
    }

    private function buildHeaders(array $headers): array {
        return array_map(fn($key, $value) => "$key: $value", array_keys($headers), $headers);
    }

    public function fetch(string $url): ?string {
        curl_setopt($this->session, CURLOPT_URL, $url);
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $contentType = curl_getinfo($this->session, CURLINFO_CONTENT_TYPE);
        if (strpos($contentType, 'charset=') !== false) {
            $encoding = strtoupper(preg_match('/charset=([^;]+)/i', $contentType, $matches) ? $matches[1] : 'UTF-8');
            if ($encoding !== 'UTF-8') {
                $response = mb_convert_encoding($response, 'UTF-8', $encoding);
            }
        }

        return $response;
    }

    public function __destruct() {
        if ($this->session) {
            curl_close($this->session);
        }
    }
}

/**
 * 内容提取接口
 */
interface ContentExtractorInterface {
    public function extractVideos(string $html, int $limit = 0): array;
    public function extractSearchResults(string $html): array;
    public function extractPlayInfo(string $html, string $vid): array;
    public function extractTitle(string $html): string;
    public function extractPic(string $html): string;
    public function extractDesc(string $html): string;
    public function extractRemarks(string $html): string;
}

/**
 * 永乐视频内容提取器
 */
class YongLeContentExtractor implements ContentExtractorInterface {
    private $host;

    public function __construct(string $host) {
        $this->host = $host;
    }

    public function extractVideos(string $html, int $limit = 0): array {
        $videos = [];
        $pattern = '/<a href="\/voddetail\/(\d+)\/".*?title="([^"]+)".*?<div class="module-item-note">([^<]+)<\/div>.*?data-original="([^"]+)"/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $pic = trim($match[4]);
                if (strpos($pic, '/') === 0) {
                    $pic = $this->host . ltrim($pic, '/');
                }
                $videos[] = [
                    'vod_id' => trim($match[1]),
                    'vod_name' => trim($match[2]),
                    'vod_pic' => $pic,
                    'vod_remarks' => trim($match[3])
                ];
            }
        }

        return $limit > 0 ? array_slice($videos, 0, $limit) : $videos;
    }

    public function extractSearchResults(string $html): array {
        $videos = [];
        $pattern = '/<a href="\/voddetail\/(\d+)\/".*?title="([^"]+)".*?data-original="([^"]+)".*?<div class="module-item-note">([^<]*)<\/div>/is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $pic = trim($match[3]);
                if (strpos($pic, '/') === 0) {
                    $pic = $this->host . ltrim($pic, '/');
                }
                $videos[] = [
                    'vod_id' => trim($match[1]),
                    'vod_name' => trim($match[2]),
                    'vod_pic' => $pic,
                    'vod_remarks' => trim($match[4])
                ];
            }
        }

        return $videos;
    }

    public function extractPlayInfo(string $html, string $vid): array {
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

    private function getLineId(string $html, string $vid, string $lineName): string {
        $pattern = '/<a[^>]*href="\/play\/' . $vid . '-(\d+)-1\/"[^>]*>.*?<span>' . preg_quote($lineName, '/') . '<\/span>/is';
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }

        $lineIdMap = [
            '全球3线' => '3',
            '大陆0线' => '1',
            '大陆3线' => '4',
            '大陆5线' => '2',
            '大陆6线' => '3'
        ];
        return $lineIdMap[$lineName] ?? '1';
    }

    public function extractTitle(string $html): string {
        if (preg_match('/<meta property="og:title" content="([^"]+)-[^-]+$"/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    public function extractPic(string $html): string {
        if (preg_match('/<meta property="og:image" content="([^"]+)"/is', $html, $matches)) {
            $pic = trim($matches[1]);
            if ($pic && strpos($pic, '/') === 0) {
                return $this->host . ltrim($pic, '/');
            }
            return $pic;
        }
        return '';
    }

    public function extractDesc(string $html): string {
        if (preg_match('/<meta property="og:description" content="([^"]+)"/is', $html, $matches)) {
            return trim($matches[1]);
        }
        return '暂无简介';
    }

    public function extractRemarks(string $html): string {
        $year = preg_match('/<a title="(\d+)" href="\/vodshow\/\d+-----------\1\/">/is', $html, $matches) ? $matches[1] : '未知年份';
        $area = preg_match('/<a title="([^"]+)" href="\/vodshow\/\d+-%E5%A2%A8%E8%A5%BF%E5%93%A5----------\/">/is', $html, $matches) ? $matches[1] : '未知产地';
        $typeStr = preg_match('/vod_class":"([^"]+)"/is', $html, $matches) ? str_replace(',', '/', $matches[1]) : '未知类型';
        return "$year | $area | $typeStr";
    }
}

/**
 * API处理器，负责路由和响应格式化
 */
class ApiHandler {
    private $httpClient;
    private $extractor;

    public function __construct(HttpClientInterface $httpClient, ContentExtractorInterface $extractor) {
        $this->httpClient = $httpClient;
        $this->extractor = $extractor;
    }

    /**
     * 获取首页数据
     */
    public function getHome(): array {
        $result = [
            'class' => [
                ['type_id' => '1', 'type_name' => '电影'],
                ['type_id' => '2', 'type_name' => '剧集'],
                ['type_id' => '3', 'type_name' => '综艺'],
                ['type_id' => '4', 'type_name' => '动漫']
            ],
            'filters' => $this->getFilters(),
            'list' => [],
            'style' => ['type' => 'rect', 'ratio' => 1.33]
        ];

        $rsp = $this->httpClient->fetch(Config::HOST);
        if ($rsp) {
            $result['list'] = $this->extractor->extractVideos($rsp, 20);
        }

        return $result;
    }

    /**
     * 获取分类内容
     * @param string $tid 分类ID
     * @param string $page 页码
     * @param array $filters 筛选条件
     */
    public function getCategory(string $tid, string $page, array $filters = []): array {
        $isSubRequest = isset($filters['is_sub']) && $filters['is_sub'] === 'true';
        $page = max(1, intval($page));

        if ($isSubRequest) {
            // 二级分类内容
            $url = $page > 1 ? Config::HOST . "vodtype/{$tid}/page/{$page}/" : Config::HOST . "vodtype/{$tid}/";
            $result = [
                'list' => [],
                'page' => $page,
                'pagecount' => 99,
                'limit' => 20,
                'total' => 1980,
                'style' => ['type' => 'oval', 'ratio' => 0.75]
            ];
            $rsp = $this->httpClient->fetch($url);
            if ($rsp) {
                $result['list'] = $this->extractor->extractVideos($rsp);
            }
            return $result;
        }

        // 一级分类（显示为文件夹）
        $subCategories = [
            '1' => [
                ['vod_id' => 'movie_action', 'vod_name' => '动作片', 'vod_pic' => Config::HOST . 'static/images/action.jpg'],
                ['vod_id' => 'movie_comedy', 'vod_name' => '喜剧片', 'vod_pic' => Config::HOST . 'static/images/comedy.jpg']
            ],
            '2' => [
                ['vod_id' => 'series_cn', 'vod_name' => '国产剧', 'vod_pic' => Config::HOST . 'static/images/series_cn.jpg'],
                ['vod_id' => 'series_hk', 'vod_name' => '港台剧', 'vod_pic' => Config::HOST . 'static/images/series_hk.jpg']
            ],
            '3' => [
                ['vod_id' => 'variety_cn', 'vod_name' => '内地综艺', 'vod_pic' => Config::HOST . 'static/images/variety_cn.jpg'],
                ['vod_id' => 'variety_hk', 'vod_name' => '港台综艺', 'vod_pic' => Config::HOST . 'static/images/variety_hk.jpg']
            ],
            '4' => [
                ['vod_id' => 'anime_cn', 'vod_name' => '国产动漫', 'vod_pic' => Config::HOST . 'static/images/anime_cn.jpg'],
                ['vod_id' => 'anime_jp', 'vod_name' => '日本动漫', 'vod_pic' => Config::HOST . 'static/images/anime_jp.jpg']
            ]
        ];

        return [
            'is_sub' => true,
            'list' => $subCategories[$tid] ?? [],
            'page' => 1,
            'pagecount' => 1,
            'limit' => 20,
            'total' => count($subCategories[$tid] ?? []),
            'style' => ['type' => 'rect', 'ratio' => 2.0]
        ];
    }

    /**
     * 获取视频详情
     * @param string $ids 视频ID（逗号分隔）
     */
    public function getDetail(string $ids): array {
        $result = ['list' => []];
        $vid = explode(',', $ids)[0];

        if (empty($vid)) {
            return ['error' => '无效的视频ID'];
        }

        $rsp = $this->httpClient->fetch(Config::HOST . "voddetail/{$vid}/");
        if (!$rsp) {
            return ['error' => '无法获取详情数据'];
        }

        list($playFrom, $playUrl) = $this->extractor->extractPlayInfo($rsp, $vid);

        if (!empty($playFrom)) {
            $result['list'][] = [
                'vod_id' => $vid,
                'vod_name' => $this->extractor->extractTitle($rsp),
                'vod_pic' => $this->extractor->extractPic($rsp),
                'vod_content' => $this->extractor->extractDesc($rsp),
                'vod_remarks' => $this->extractor->extractRemarks($rsp),
                'vod_play_from' => implode('$$$', $playFrom),
                'vod_play_url' => implode('$$$', $playUrl)
            ];
        }

        return $result;
    }

    /**
     * 搜索内容
     * @param string $keyword 搜索关键词
     * @param string $page 页码
     */
    public function search(string $keyword, string $page): array {
        $result = [
            'list' => [],
            'page' => max(1, intval($page)),
            'pagecount' => 99,
            'limit' => 20,
            'total' => 1980
        ];

        if (empty($keyword)) {
            return ['error' => '搜索关键词不能为空'];
        }

        $searchKey = urlencode($keyword);
        $url = $page > 1 ? Config::HOST . "vodsearch/{$searchKey}-------------/page/{$page}/" : Config::HOST . "vodsearch/{$searchKey}-------------/";
        $rsp = $this->httpClient->fetch($url);
        if ($rsp) {
            $result['list'] = $this->extractor->extractSearchResults($rsp);
        }

        return $result;
    }

    /**
     * 获取播放地址
     * @param string $flag 线路标识
     * @param string $id 播放ID
     */
    public function getPlay(string $flag, string $id): array {
        $result = ['parse' => 1, 'playUrl' => '', 'url' => ''];

        if (empty($id) || strpos($id, '-') === false) {
            return ['error' => '无效的播放ID'];
        }

        $rsp = $this->httpClient->fetch(Config::HOST . "play/{$id}/");
        if (!$rsp) {
            return ['error' => '无法获取播放数据'];
        }

        if (preg_match('/var player_aaaa=.*?"url":"([^"]+\.m3u8)"/is', $rsp, $matches)) {
            $realUrl = str_replace(['\u002F', '\/'], '/', $matches[1]);
            $result['parse'] = 0;
            $result['playUrl'] = $realUrl;
            $result['url'] = $realUrl;
        } else {
            $result['url'] = Config::HOST . "play/{$id}/";
        }

        return $result;
    }

    /**
     * 获取筛选条件
     */
    private function getFilters(): array {
        return [
            '1' => [
                ['key' => 'class', 'name' => '类型', 'value' => [
                    ['n' => '全部', 'v' => ''],
                    ['n' => '动作片', 'v' => '6'],
                    ['n' => '喜剧片', 'v' => '7'],
                    ['n' => '爱情片', 'v' => '8'],
                    ['n' => '科幻片', 'v' => '9'],
                    ['n' => '恐怖片', 'v' => '11']
                ]]
            ],
            '2' => [
                ['key' => 'class', 'name' => '类型', 'value' => [
                    ['n' => '全部', 'v' => ''],
                    ['n' => '国产剧', 'v' => '13'],
                    ['n' => '港台剧', 'v' => '14'],
                    ['n' => '日剧', 'v' => '15'],
                    ['n' => '韩剧', 'v' => '33'],
                    ['n' => '欧美剧', 'v' => '16']
                ]]
            ],
            '3' => [
                ['key' => 'class', 'name' => '类型', 'value' => [
                    ['n' => '全部', 'v' => ''],
                    ['n' => '内地综艺', 'v' => '27'],
                    ['n' => '港台综艺', 'v' => '28'],
                    ['n' => '日本综艺', 'v' => '29'],
                    ['n' => '韩国综艺', 'v' => '36']
                ]]
            ],
            '4' => [
                ['key' => 'class', 'name' => '类型', 'value' => [
                    ['n' => '全部', 'v' => ''],
                    ['n' => '国产动漫', 'v' => '31'],
                    ['n' => '日本动漫', 'v' => '32'],
                    ['n' => '欧美动漫', 'v' => '42'],
                    ['n' => '其他动漫', 'v' => '43']
                ]]
            ]
        ];
    }
}

/**
 * 主入口，处理API请求
 */
try {
    // 获取请求参数
    $ac = $_GET['ac'] ?? 'detail';
    $t = $_GET['t'] ?? '';
    $pg = $_GET['pg'] ?? '1';
    $f = $_GET['f'] ?? '';
    $ids = $_GET['ids'] ?? '';
    $wd = $_GET['wd'] ?? '';
    $flag = $_GET['flag'] ?? '';
    $id = $_GET['id'] ?? '';

    // 初始化依赖
    $httpClient = new CurlHttpClient();
    $extractor = new YongLeContentExtractor(Config::HOST);
    $apiHandler = new ApiHandler($httpClient, $extractor);

    // 根据操作分发请求
    switch ($ac) {
        case 'detail':
            if (!empty($ids)) {
                echo json_encode($apiHandler->getDetail($ids));
            } elseif (!empty($t)) {
                $filters = !empty($f) ? json_decode($f, true) : [];
                echo json_encode($apiHandler->getCategory($t, $pg, $filters));
            } else {
                echo json_encode($apiHandler->getHome());
            }
            break;

        case 'search':
            echo json_encode($apiHandler->search($wd, $pg));
            break;

        case 'play':
            echo json_encode($apiHandler->getPlay($flag, $id));
            break;

        default:
            echo json_encode(['error' => '未知操作: ' . $ac]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => '服务器错误: ' . $e->getMessage()]);
}