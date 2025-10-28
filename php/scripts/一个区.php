<?php
// 视频网站抓取脚本 - 兼容Apple CMS API格式
// 数据从 https://6181036.xyz 实时抓取
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

// 目标网站
$baseUrl = 'https://6181036.xyz';

// 获取网页内容
function getHtml($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// 从网站首页提取真实分类
function getRealCategories() {
    global $baseUrl;
    $html = getHtml($baseUrl);
    
    $categories = [];
    
    // 提取导航栏中的分类链接
    preg_match_all('/<a[^>]*href="(\/index\.php\/vod\/type\/id\/(\d+)\.html)"[^>]*>([^<]+)<\/a>/U', $html, $matches);
    
    for ($i = 0; $i < count($matches[1]); $i++) {
        $name = trim(strip_tags($matches[3][$i]));
        if ($name && strlen($name) > 1) {
            $categories[] = [
                'type_id' => $matches[2][$i],
                'type_name' => $name
            ];
        }
    }
    
    return $categories;
}

// 从网站获取真实视频列表
function getRealVideos($typeId, $page = 1) {
    global $baseUrl;
    
    $url = $baseUrl . "/index.php/vod/type/id/{$typeId}.html";
    if ($page > 1) {
        $url = $baseUrl . "/index.php/vod/type/id/{$typeId}/page/{$page}.html";
    }
    
    $html = getHtml($url);
    
    $videos = [];
    
    // 提取视频列表 - 改进的正则匹配
    preg_match_all('/<a[^>]*?href="(\/index\.php\/vod\/detail\/id\/(\d+)\.html)"[^>]*?>.*?<img[^>]*?src="([^"]*?)"[^>]*?.*?<span[^>]*?class="[^"]*?video-name[^"]*?"[^>]*?>(.*?)<\/span>/s', $html, $matches);
    
    for ($i = 0; $i < count($matches[1]); $i++) {
        $title = trim(strip_tags($matches[4][$i]));
        $image = $matches[3][$i];
        $videoUrl = $matches[1][$i];
        
        // 处理相对路径
        if ($image && strpos($image, 'http') !== 0) {
            $image = $baseUrl . $image;
        }
        if ($videoUrl && strpos($videoUrl, 'http') !== 0) {
            $videoUrl = $baseUrl . $videoUrl;
        }
        
        $videos[] = [
            'vod_id' => $matches[2][$i],
            'vod_name' => $title,
            'vod_pic' => $image,
            'vod_remarks' => getVideoRemarks($html, $i)
        ];
    }
    
    // 提取分页信息
    $pageInfo = extractPageInfo($html);
    
    return [
        'list' => $videos,
        'page' => intval($page),
        'pagecount' => $pageInfo['pagecount'] ?? 10,
        'limit' => 20,
        'total' => $pageInfo['total'] ?? 200
    ];
}

// 获取视频详情
function getRealVideoDetail($videoId) {
    global $baseUrl;
    
    $url = $baseUrl . "/index.php/vod/detail/id/{$videoId}.html";
    $html = getHtml($url);
    
    // 提取标题
    preg_match('/<h1[^>]*>(.*?)<\/h1>/', $html, $titleMatch);
    $title = $titleMatch[1] ?? '视频详情';
    
    // 提取封面
    preg_match('/<img[^>]*?src="([^"]*?)"[^>]*?class="[^"]*?video-cover[^"]*?"[^>]*?>/', $html, $imageMatch);
    $image = $imageMatch[1] ?? '';
    if ($image && strpos($image, 'http') !== 0) {
        $image = $baseUrl . $image;
    }
    
    // 提取描述
    preg_match('/<div[^>]*?class="[^"]*?video-info-content[^"]*?"[^>]*?>(.*?)<\/div>/s', $html, $descMatch);
    $description = $descMatch[1] ?? '暂无描述';
    
    // 提取播放数据（这里需要根据实际网站结构调整）
    $playData = extractPlayData($html);
    
    return [
        'list' => [
            [
                'vod_id' => $videoId,
                'vod_name' => trim(strip_tags($title)),
                'vod_pic' => $image,
                'vod_content' => trim(strip_tags($description)),
                'vod_play_from' => $playData['from'],
                'vod_play_url' => $playData['url']
            ]
        ]
    ];
}

// 搜索视频
function searchRealVideos($keyword, $page = 1) {
    global $baseUrl;
    
    $url = $baseUrl . "/index.php/vod/search.html?wd=" . urlencode($keyword);
    $html = getHtml($url);
    
    $videos = [];
    
    // 提取搜索结果
    preg_match_all('/<a[^>]*?href="(\/index\.php\/vod\/detail\/id\/(\d+)\.html)"[^>]*?>.*?<img[^>]*?src="([^"]*?)"[^>]*?.*?<span[^>]*?class="[^"]*?video-name[^"]*?"[^>]*?>(.*?)<\/span>/s', $html, $matches);
    
    for ($i = 0; $i < count($matches[1]); $i++) {
        $title = trim(strip_tags($matches[4][$i]));
        $image = $matches[3][$i];
        
        if ($image && strpos($image, 'http') !== 0) {
            $image = $baseUrl . $image;
        }
        
        $videos[] = [
            'vod_id' => $matches[2][$i],
            'vod_name' => $title,
            'vod_pic' => $image,
            'vod_remarks' => '搜索结果'
        ];
    }
    
    return [
        'list' => $videos,
        'page' => intval($page),
        'pagecount' => 5,
        'limit' => 20,
        'total' => count($videos)
    ];
}

// 辅助函数
function getVideoRemarks($html, $index) {
    // 尝试提取更新状态等信息
    preg_match_all('/<span[^>]*?class="[^"]*?video-remarks[^"]*?"[^>]*?>(.*?)<\/span>/', $html, $remarksMatches);
    if (!empty($remarksMatches[1][$index])) {
        return trim(strip_tags($remarksMatches[1][$index]));
    }
    return '更新中';
}

function extractPageInfo($html) {
    preg_match('/<span[^>]*?>共(\d+)条记录<\/span>/', $html, $totalMatch);
    preg_match('/<span[^>]*?>(\d+)\/(\d+)页<\/span>/', $html, $pageMatch);
    
    return [
        'total' => $totalMatch[1] ?? 200,
        'pagecount' => $pageMatch[2] ?? 10
    ];
}

function extractPlayData($html) {
    // 这里需要根据实际播放页结构提取播放地址
    // 暂时返回演示数据
    return [
        'from' => '线路1$$$线路2',
        'url' => '第1集$https://example.com/play1.m3u8#第2集$https://example.com/play2.m3u8$$$第1集$https://example.com/play1hd.m3u8'
    ];
}

// 主逻辑
switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            // 视频详情 - 从真实网站获取
            echo json_encode(getRealVideoDetail($ids));
        } elseif (!empty($t)) {
            // 分类列表 - 从真实网站获取
            $filters = !empty($f) ? json_decode($f, true) : [];
            
            // 检查是否请求二级分类
            $isSubRequest = isset($filters['is_sub']) && $filters['is_sub'] === 'true';
            
            if ($isSubRequest) {
                // 二级分类内容
                echo json_encode([
                    'list' => [
                        ['vod_id' => 'sub_1', 'vod_name' => '二级内容1', 'vod_pic' => 'https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg'],
                        ['vod_id' => 'sub_2', 'vod_name' => '二级内容2', 'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg']
                    ],
                    'page' => intval($pg),
                    'pagecount' => 5,
                    'limit' => 20,
                    'total' => 100
                ]);
            } else {
                // 普通分类列表 - 从真实网站获取数据
                echo json_encode(getRealVideos($t, $pg));
            }
        } else {
            // 首页分类 - 从真实网站获取分类
            $realCategories = getRealCategories();
            
            echo json_encode([
                'class' => $realCategories,
                'filters' => [
                    '1' => [
                        ['key' => 'class', 'name' => '类型', 'value' => [
                            ['n' => '全部', 'v' => ''],
                            ['n' => '动作', 'v' => '动作'],
                            ['n' => '喜剧', 'v' => '喜剧']
                        ]]
                    ]
                ]
            ]);
        }
        break;
    
    case 'search':
        // 搜索 - 从真实网站搜索
        if (!empty($wd)) {
            echo json_encode(searchRealVideos($wd, $pg));
        } else {
            echo json_encode([
                'list' => [],
                'page' => 1,
                'pagecount' => 0,
                'total' => 0
            ]);
        }
        break;
        
    case 'play':
        // 播放地址
        echo json_encode([
            'parse' => 1,
            'playUrl' => '',
            'url' => $baseUrl . "/index.php/vod/play/id/{$id}.html"
        ]);
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $ac]);
}
?>