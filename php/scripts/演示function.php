<?php
// PHP爬虫脚本
// 每个函数对应一个接口，参数在函数头有中文注释

/**
 * 首页内容
 * @return JSON
 */
function homeContent() {
    return json_encode([
        'class' => [
            ['type_id' => '1', 'type_name' => '电影'],
            ['type_id' => '2', 'type_name' => '电视剧']
        ],
        'list' => [
            ['vod_id' => 'h1', 'vod_name' => '首页视频1', 'vod_pic' => 'https://example.com/home1.jpg'],
            ['vod_id' => 'h2', 'vod_name' => '首页视频2', 'vod_pic' => 'https://example.com/home2.jpg']
        ]
    ]);
}

/**
 * 二级分类内容
 * @param string $tid 分类ID
 * @param string $subId 二级分类ID
 */
function subCategory($tid, $subId) {
    return json_encode([
        'list' => [
            ['vod_id' => $tid . '_' . $subId . '_1', 'vod_name' => '二级分类内容1', 'vod_pic' => 'https://example.com/sub1.jpg'],
            ['vod_id' => $tid . '_' . $subId . '_2', 'vod_name' => '二级分类内容2', 'vod_pic' => 'https://example.com/sub2.jpg']
        ]
    ]);
}

/**
 * 分类内容
 * @param string $tid 分类ID
 * @param int $pg 页码
 * @param string $filter 筛选条件
 * @param string $extend 扩展参数
 */
function categoryContent($tid, $pg, $filter, $extend) {
    return json_encode([
        'list' => [
            ['vod_id' => $tid . '_c1', 'vod_name' => '分类视频1', 'vod_pic' => 'https://example.com/cat1.jpg'],
            ['vod_id' => $tid . '_c2', 'vod_name' => '分类视频2', 'vod_pic' => 'https://example.com/cat2.jpg']
        ],
        'page' => intval($pg),
        'pagecount' => 5,
        'limit' => 20,
        'total' => 100
    ]);
}

/**
 * 详情内容
 * @param string $ids 视频ID
 */
function detailContent($ids) {
    return json_encode([
        'list' => [[
            'vod_id' => $ids,
            'vod_name' => '详情: ' . $ids,
            'vod_pic' => 'https://example.com/detail.jpg',
            'vod_content' => '这是 ' . $ids . ' 的详细描述'
        ]]
    ]);
}

/**
 * 搜索内容
 * @param string $wd 搜索关键词
 * @param bool $quick 是否快速搜索
 */
function searchContent($wd, $quick) {
    return json_encode([
        'list' => [[
            'vod_id' => 's_' . $wd,
            'vod_name' => '搜索结果: ' . $wd,
            'vod_pic' => 'https://example.com/search.jpg'
        ]]
    ]);
}

/**
 * 播放内容
 * @param string $id 视频ID
 */
function playerContent($id) {
    return json_encode([
        'parse' => 1,
        'url' => 'https://haokan.baidu.com/v?vid=' . $id
    ]);
}

?>