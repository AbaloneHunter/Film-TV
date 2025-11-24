<?php
// PHP爬虫脚本
// 使用类封装接口，每个方法对应一个操作，方法头有中文注释

class DemoSpider {

    /**
     * 首页内容
     * @return JSON
     */
    public function homeContent() {
        $data = [
            'class' => [
                ['type_id' => '1', 'type_name' => '电影'],
                ['type_id' => '2', 'type_name' => '电视剧']
            ],
            'list' => [
                ['vod_id' => 'h1', 'vod_name' => '首页视频1', 'vod_pic' => 'https://example.com/home1.jpg'],
                ['vod_id' => 'h2', 'vod_name' => '首页视频2', 'vod_pic' => 'https://example.com/home2.jpg']
            ],
            'style' => [ 'type' => 'rect', 'ratio' => 1.33 ]
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 二级分类内容
     * @param string $t 分类ID
     * @param string $subId 二级分类ID
     */
    public function subCategory($t, $subId) {
        $data = [
            'list' => [
                ['vod_id' => $t . '_' . $subId . '_1', 'vod_name' => '二级分类内容1', 'vod_pic' => 'https://example.com/sub1.jpg'],
                ['vod_id' => $t . '_' . $subId . '_2', 'vod_name' => '二级分类内容2', 'vod_pic' => 'https://example.com/sub2.jpg']
            ],
            'style' => [ 'type' => 'oval', 'ratio' => 0.75 ]
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 分类内容
     * @param string $t 分类ID
     * @param int $pg 页码
     * @param string $f 筛选条件
     * @param string $extend 扩展参数
     */
    public function categoryContent($t, $pg, $f, $extend) {
        if ($t == '1') {
            $data = [
                'is_sub' => true,
                'list' => [
                    ['vod_id' => 'movie_action', 'vod_name' => '动作片', 'vod_pic' => 'https://example.com/action.jpg'],
                    ['vod_id' => 'movie_comedy', 'vod_name' => '喜剧片', 'vod_pic' => 'https://example.com/comedy.jpg']
                ],
                'style' => [ 'type' => 'rect', 'ratio' => 2.0 ]
            ];
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $data = [
            'list' => [
                ['vod_id' => $t . '_c1', 'vod_name' => '分类视频1', 'vod_pic' => 'https://example.com/cat1.jpg'],
                ['vod_id' => $t . '_c2', 'vod_name' => '分类视频2', 'vod_pic' => 'https://example.com/cat2.jpg']
            ],
            'page' => intval($pg),
            'pagecount' => 5,
            'limit' => 20,
            'total' => 100,
            'style' => [ 'type' => 'round', 'ratio' => 1.0 ]
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 详情内容
     * @param string $ids 视频ID
     */
    public function detailContent($ids) {
        $data = [
            'list' => [[
                'vod_id' => $ids,
                'vod_name' => '详情: ' . $ids,
                'vod_pic' => 'https://example.com/detail.jpg',
                'vod_content' => '这是 ' . $ids . ' 的详细描述'
            ]]
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 搜索内容
     * @param string $wd 搜索关键词
     * @param bool $quick 是否快速搜索
     */
    public function searchContent($wd, $quick) {
        $data = [
            'list' => [[
                'vod_id' => 's_' . $wd,
                'vod_name' => '搜索结果: ' . $wd,
                'vod_pic' => 'https://example.com/search.jpg'
            ]]
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 播放内容
     * @param string $id 视频ID
     */
    public function playerContent($id) {
        $data = [
            'parse' => 1,
            'url' => 'https://haokan.baidu.com/v?vid=' . $id
        ];
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}

?>