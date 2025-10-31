<?php
// 示例PHP爬虫脚本 - 兼容Apple CMS API格式
// 支持二级分类功能和自定义样式
//
// 二级分类实现说明：
// 1. 当返回的JSON根级包含 'is_sub' => true 时，表示当前列表是二级分类
// 2. 二级分类项目会被标记为文件夹图标，点击后会重新调用categoryContent
// 3. 点击二级分类时，会在筛选参数f中包含 'is_sub' => 'true'
// 4. 根据is_sub参数判断是返回二级分类还是实际内容
//
// 自定义样式说明：
// 1. style字段应放在JSON最外层，会应用到所有列表项
// 2. type: 'rect'(矩形), 'oval'(椭圆), 'round'(圆形)
// 3. ratio: 宽高比例，如1.5表示3:2，0.67表示2:3
//
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
            $data = [
                'list' => [
                    [
                        'vod_id' => $ids,
                        'vod_name' => '测试视频详情',
                        'vod_pic' => 'https://2uspicc12tche.hitv.app/350/upload/vod/20240415-1/2636d5210e5cf7a6f0cff5c737e6c7b5.webp',
                        'vod_content' => '这是一个测试视频的详细描述',
                        'vod_play_from' => '清凉妹子$$$线路2',
                        'vod_play_url' => '第1集$12767287836095919439#第2集$https://example.com/play2.m3u8$$$第1集$https://example2.com/play1.m3u8'
                    ]
                ]
            ];
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (!empty($t)) {
            // 分类列表
            $filters = !empty($f) ? json_decode($f, true) : [];
            
            // 检查是否请求二级分类
            $isSubRequest = isset($filters['is_sub']) && $filters['is_sub'] === 'true';
            
            if ($isSubRequest) {
                // 二级分类：返回实际内容
                $data = [
                    'list' => [
                        ['vod_id' => 'sub_1', 'vod_name' => '二级内容1', 'vod_pic' => 'https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg'],
                        ['vod_id' => 'sub_2', 'vod_name' => '二级内容2', 'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg']
                    ],
                    'page' => intval($pg),
                    'pagecount' => 5,
                    'limit' => 20,
                    'total' => 100,
                    // 可选：为二级内容设置不同的样式
                    'style' => [
                        'type' => 'oval',
                        'ratio' => 0.75
                    ]
                ];
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } elseif ($t == '1') {
                // 演示：电影分类包含二级分类，设置根级is_sub=true
                $data = [
                    'is_sub' => true,   // 标识这是二级分类列表
                    'list' => [
                        ['vod_id' => 'movie_action', 'vod_name' => '动作片', 'vod_pic' => 'https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg'],
                        ['vod_id' => 'movie_comedy', 'vod_name' => '喜剧片', 'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg']
                    ],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 20,
                    'total' => 2,
                    // 文件夹样式：适合二级分类导航
                    'style' => [
                        'type' => 'rect',
                        'ratio' => 2.0
                    ]
                ];
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                // 普通分类列表
                $data = [
                    'list' => [
                        ['vod_id' => '1', 'vod_name' => '清凉视频', 'vod_pic' => 'https://2uspicc12tche.hitv.app/350/upload/vod/20240415-1/2636d5210e5cf7a6f0cff5c737e6c7b5.webp'],
                        ['vod_id' => '2', 'vod_name' => '测试视频2', 'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg']
                    ],
                    'filters' => [
                        '2' => [
                            ['key' => 'class', 'name' => '类型', 'value' => [
                                ['n' => '全部', 'v' => ''],
                                ['n' => '动作', 'v' => '动作'],
                                ['n' => '喜剧', 'v' => '喜剧']
                            ]]
                        ]
                    ],
                    'page' => intval($pg),
                    'pagecount' => 10,
                    'limit' => 20,
                    'total' => 200,
                ];
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        } else {
            // 首页分类
            $data = [
                'class' => [
                    ['type_id' => '1', 'type_name' => '电影'],
                    ['type_id' => '2', 'type_name' => '电视剧'],
                    ['type_id' => '3', 'type_name' => '综艺'],
                    ['type_id' => '4', 'type_name' => '动漫']
                ],
                'list' => [
                    ['vod_id' => 'home_1', 'vod_name' => '首页推荐视频1', 'vod_pic' => 'https://img9.doubanio.com/view/photo/m_ratio_poster/public/p2578045524.jpg', 'vod_remarks' => '演示备注1'],
                    ['vod_id' => 'home_2', 'vod_name' => '首页推荐视频2', 'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg', 'vod_remarks' => '演示备注2']
                ],
                'filters' => [
                    '1' => [
                        ['key' => 'class', 'name' => '类型', 'value' => [
                            ['n' => '全部', 'v' => ''],
                            ['n' => '动作', 'v' => '动作'],
                            ['n' => '喜剧', 'v' => '喜剧']
                        ]]
                    ]
                ],
                // 自定义样式（可选）- 在最外层定义
                'style' => [
                    'type' => 'rect',
                    'ratio' => 1.33
                ]
            ];
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        break;
    
    case 'search':
        $data = [
            'list' => [
                ['vod_id' => 's1', 'vod_name' => '搜索结果: ' . $wd, 'vod_pic' => 'https://2uspicc12tche.hitv.app/350/upload/vod/20240415-1/2636d5210e5cf7a6f0cff5c737e6c7b5.webp']
            ],
            'page' => intval($pg),
            'pagecount' => 1,
            'limit' => 20,
            'total' => 1
        ];
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;
        
    case 'play':
        $data = [
            'parse' => 1,  // 0=直接播放, 1=需要解析
            'playUrl' => '',  // 如果 parse=0，这里填实际播放地址
            'url' => 'https://haokan.baidu.com/v?vid=' . $id  // 如果 parse=1，这里填需要解析的地址
        ];
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        break;
    
    default:
        $data = ['error' => 'Unknown action: ' . $ac];
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
?>