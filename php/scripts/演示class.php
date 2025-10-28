<?php
// PHP爬虫脚本
// 使用类封装接口，每个方法对应一个操作，方法头有中文注释

class DemoSpider {

    /**
     * 首页内容
     * @return JSON
     */
    public function homeContent() {
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
     * 分类内容
     * @param string $tid 分类ID
     * @param int $pg 页码
     */
    public function categoryContent($tid, $pg) {
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
    public function detailContent($ids) {
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
     */
    public function searchContent($wd) {
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
    public function playerContent($id) {
        return json_encode([
            'parse' => 1,
            'url' => 'https://haokan.baidu.com/v?vid=' . $id
        ]);
    }
}

?>