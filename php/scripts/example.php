<?php
/**
 * TVBox PHP 爬虫脚本示例
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

/**
 * 首页数据
 * 返回分类列表和推荐内容
 */
function getHome() {
    return [
        'class' => [
            ['type_id' => '1', 'type_name' => '电影'],
            ['type_id' => '2', 'type_name' => '电视剧'],
            ['type_id' => '3', 'type_name' => '综艺'],
            ['type_id' => '4', 'type_name' => '动漫'],
        ],
        'list' => [
            [
                'vod_id' => '1',
                'vod_name' => '示例电影1',
                'vod_pic' => 'https://via.placeholder.com/300x400.png?text=Movie1',
                'vod_remarks' => 'HD'
            ],
            [
                'vod_id' => '2',
                'vod_name' => '示例电影2',
                'vod_pic' => 'https://via.placeholder.com/300x400.png?text=Movie2',
                'vod_remarks' => '4K'
            ],
        ]
    ];
}

/**
 * 分类列表
 * @param string $tid 分类ID
 * @param string $page 页码
 */
function getCategory($tid, $page) {
    // 这里应该根据 $tid 和 $page 获取实际数据
    // 示例返回模拟数据
    
    $list = [];
    for ($i = 1; $i <= 20; $i++) {
        $list[] = [
            'vod_id' => $tid . '_' . (($page - 1) * 20 + $i),
            'vod_name' => "分类{$tid}-视频{$i}",
            'vod_pic' => "https://via.placeholder.com/300x400.png?text=Video{$i}",
            'vod_remarks' => 'HD'
        ];
    }
    
    return [
        'page' => intval($page),
        'pagecount' => 10,
        'limit' => 20,
        'total' => 200,
        'list' => $list
    ];
}

/**
 * 视频详情
 * @param string $ids 视频ID（多个用逗号分隔）
 */
function getDetail($ids) {
    $idArray = explode(',', $ids);
    $list = [];
    
    foreach ($idArray as $id) {
        $list[] = [
            'vod_id' => $id,
            'vod_name' => "视频 {$id}",
            'vod_pic' => 'https://via.placeholder.com/300x400.png?text=Detail',
            'vod_remarks' => 'HD',
            'vod_year' => '2024',
            'vod_area' => '中国大陆',
            'vod_director' => '导演名称',
            'vod_actor' => '演员A, 演员B, 演员C',
            'vod_content' => '这是视频的详细介绍内容...',
            'vod_play_from' => '线路1$$$线路2',
            'vod_play_url' => implode('#', [
                '第1集$https://example.com/video1.m3u8',
                '第2集$https://example.com/video2.m3u8',
                '第3集$https://example.com/video3.m3u8',
            ]) . '$$$' . implode('#', [
                '第1集$https://backup.com/video1.m3u8',
                '第2集$https://backup.com/video2.m3u8',
                '第3集$https://backup.com/video3.m3u8',
            ])
        ];
    }
    
    return ['list' => $list];
}

/**
 * 搜索
 * @param string $keyword 关键词
 * @param string $page 页码
 */
function search($keyword, $page) {
    // 这里应该根据 $keyword 和 $page 搜索实际数据
    // 示例返回模拟数据
    
    $list = [];
    for ($i = 1; $i <= 10; $i++) {
        $list[] = [
            'vod_id' => 'search_' . $i,
            'vod_name' => "{$keyword} - 搜索结果{$i}",
            'vod_pic' => "https://via.placeholder.com/300x400.png?text=Search{$i}",
            'vod_remarks' => 'HD'
        ];
    }
    
    return [
        'page' => intval($page),
        'pagecount' => 5,
        'limit' => 10,
        'total' => 50,
        'list' => $list
    ];
}

/**
 * 获取播放地址
 * @param string $flag 线路标识
 * @param string $id 播放地址ID
 */
function getPlay($flag, $id) {
    // 这里应该解析实际的播放地址
    // 示例直接返回
    
    return [
        'parse' => 0,  // 0=直接播放, 1=需要解析
        'playUrl' => '',  // 如果 parse=0，这里填实际播放地址
        'url' => $id  // 如果 parse=1，这里填需要解析的地址
    ];
}
?>
