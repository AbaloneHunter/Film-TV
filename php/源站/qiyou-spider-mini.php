<?php
/**
 * TVBox PHP 爬虫脚本 - 奇优影院版 (Apple CMS API格式兼容版)
 * 支持的 action: home, category, detail, search, play
 * 兼容Apple CMS参数: ac, t, pg, f, ids, wd, flag, id
 */

// 使用Apple CMS标准参数
$ac = $_GET['ac'] ?? 'detail';  // 操作类型
$t = $_GET['t'] ?? '';          // 类型ID
$pg = $_GET['pg'] ?? '1';       // 页码
$f = $_GET['f'] ?? '';          // 筛选条件JSON
$ids = $_GET['ids'] ?? '';      // 详情ID
$wd = $_GET['wd'] ?? '';        // 搜索关键词
$flag = $_GET['flag'] ?? '';    // 播放标识
$id = $_GET['id'] ?? '';        // 播放ID

header('Content-Type: application/json; charset=utf-8');

switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            // 视频详情
            echo json_encode(getDetail($ids));
        } elseif (!empty($t)) {
            // 分类列表 - 解析筛选条件
            $filters = !empty($f) ? json_decode($f, true) : [];
            $by = $filters['by'] ?? 'time'; // 从筛选条件获取排序方式
            echo json_encode(getCategory($t, $pg, $by));
        } else {
            // 首页分类
            echo json_encode(getHome());
        }
        break;
    
    case 'search':
        echo json_encode(search($wd, $pg));
        break;
        
    case 'play':
        echo json_encode(getPlay($flag, $id));
        break;
    
    default:
        echo json_encode(['error' => 'Unknown action: ' . $ac]);
}

class QiYouSpider {
    private $base = "http://qiyoudy5.com";
    private $ch;
    private $categories = ["电影" => "1", "电视剧" => "2", "动漫" => "3", "综艺" => "4", "午夜" => "6"];

    public function __construct() {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            ]
        ]);
    }

    private function request($url, $post = null) {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if ($post) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($post));
        } else {
            curl_setopt($this->ch, CURLOPT_POST, false);
        }
        return curl_exec($this->ch) ?: null;
    }

    private function xpath($html) {
        if (!$html) return null;
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function query($xpath, $expr, $node = null) {
        try {
            $result = $node ? $xpath->query($expr, $node) : $xpath->query($expr);
            return $result ? iterator_to_array($result) : [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function val($xpath, $expr, $node = null) {
        $r = $this->query($xpath, $expr, $node);
        return $r ? ($r[0]->nodeValue ?? '') : '';
    }

    private function attr($node, $attr) {
        return $node && $node->hasAttribute($attr) ? $node->getAttribute($attr) : '';
    }

    private function parseItems($xpath, $selector) {
        $videos = [];
        foreach ($this->query($xpath, $selector) as $item) {
            $name = $this->attr($item, 'title') ?: '未知';
            $pic = $this->attr($item, 'data-original');
            $href = $this->attr($item, 'href');
            $remark = $this->val($xpath, ".//span[@class='pic-text text-right']/text()", $item);
            
            if ($href) {
                $videos[] = [
                    'vod_id' => $href,
                    'vod_name' => $name,
                    'vod_pic' => $pic,
                    'vod_remarks' => $remark ?: ''
                ];
            }
        }
        return $videos;
    }

    public function getHomeContent() {
        $html = $this->request($this->base . "/");
        $xpath = $this->xpath($html);
        if (!$xpath) return ['class' => [], 'list' => []];

        $classes = [];
        foreach ($this->categories as $name => $id) {
            $classes[] = ['type_id' => $id, 'type_name' => $name];
        }

        $videos = $this->parseItems($xpath, "//a[contains(@class,'stui-vodlist__thumb')]");
        
        return [
            'class' => $classes, 
            'list' => $videos,
            // 可选：自定义样式
            'style' => [
                'type' => 'rect',
                'ratio' => 1.33
            ]
        ];
    }

    public function getCategoryContent($tid, $pg, $by = 'time') {
        $html = $this->request($this->base . "/list/{$tid}_{$pg}.html?order={$by}");
        $xpath = $this->xpath($html);
        
        if (!$xpath) {
            return ['list' => [], 'page' => intval($pg), 'pagecount' => 1, 'limit' => 90, 'total' => 0];
        }

        $videos = $this->parseItems($xpath, "//a[contains(@class,'stui-vodlist__thumb')]");
        
        // 分页
        $pagecount = 1;
        foreach ($this->query($xpath, "//ul[contains(@class,'stui-page')]//a/@href") as $node) {
            if (preg_match('/list\/\d+_(\d+)\.html/', $node->nodeValue, $m)) {
                $pagecount = max($pagecount, intval($m[1]));
            }
        }

        return [
            'list' => $videos,
            'page' => intval($pg),
            'pagecount' => $pagecount ?: 9999,
            'limit' => 90,
            'total' => 999999,
            // 可选：为分类设置不同的样式
            'style' => [
                'type' => 'rect',
                'ratio' => 1.5
            ]
        ];
    }

    public function getDetailContent($ids) {
        $result = ['list' => []];
        
        foreach (explode(',', $ids) as $tid) {
            $html = $this->request($this->base . $tid);
            $xpath = $this->xpath($html);
            
            if (!$xpath) {
                $result['list'][] = ['vod_id' => $tid, 'vod_name' => '获取失败', 'vod_pic' => '', 'vod_content' => '解析失败'];
                continue;
            }

            // 基本信息
            $title = $this->val($xpath, "//div[contains(@class,'stui-content__detail')]//h1//text()") 
                  ?: $this->val($xpath, "//title/text()");
            if (preg_match('/《(.*?)》/', $title, $m)) $title = $m[1];
            
            $pic = $this->val($xpath, "//meta[@property='og:image']/@content");
            $area = $this->val($xpath, "//meta[@property='og:video:area']/@content");
            $director = $this->val($xpath, "//meta[@property='og:video:director']/@content");
            $actor = $this->val($xpath, "//meta[@property='og:video:actor']/@content");
            $desc = $this->val($xpath, "//meta[@property='og:description']/@content");
            
            // 播放列表
            $playFrom = [];
            $playUrl = [];
            
            foreach ($this->query($xpath, "//ul[contains(@class,'nav-tabs')]/li") as $tab) {
                $tabName = $this->val($xpath, ".//a/text()", $tab);
                $tabHref = $this->val($xpath, ".//a/@href", $tab);
                $tabId = str_replace("#", "", $tabHref);
                
                if (!$tabId) continue;
                
                $episodes = [];
                foreach ($this->query($xpath, "//div[@id='{$tabId}']//ul[contains(@class,'stui-content__playlist')]//a") as $ep) {
                    $epName = trim($this->val($xpath, "./text()", $ep)) ?: '播放';
                    $epUrl = $this->attr($ep, 'href');
                    if ($epUrl) $episodes[] = "{$epName}\${$epUrl}";
                }
                
                if ($episodes) {
                    $playFrom[] = $tabName;
                    $playUrl[] = implode('#', $episodes);
                }
            }
            
            // 备选方案
            if (!$playFrom) {
                $episodes = [];
                foreach ($this->query($xpath, "//ul[contains(@class,'stui-content__playlist')]//a") as $ep) {
                    $epName = trim($this->val($xpath, "./text()", $ep)) ?: '播放';
                    $epUrl = $this->attr($ep, 'href');
                    if ($epUrl) $episodes[] = "{$epName}\${$epUrl}";
                }
                if ($episodes) {
                    $playFrom = ['默认播放源'];
                    $playUrl = [implode('#', $episodes)];
                }
            }

            $vod = [
                'vod_id' => $tid,
                'vod_name' => $title ?: "视频_{$tid}",
                'vod_pic' => $pic,
                'vod_area' => $area,
                'vod_actor' => $actor,
                'vod_director' => $director,
                'vod_content' => $desc
            ];
            
            if ($playFrom) {
                $vod['vod_play_from'] = implode('$$$', $playFrom);
                $vod['vod_play_url'] = implode('$$$', $playUrl);
            }
            
            $result['list'][] = $vod;
        }
        
        return $result;
    }

    public function getSearchContent($key, $pg = 1) {
        $html = $this->request($this->base . "/search.php", ['searchword' => $key, 'key' => $key]);
        $xpath = $this->xpath($html);
        
        if (!$xpath) {
            return ['list' => [], 'page' => intval($pg), 'pagecount' => 1, 'limit' => 20, 'total' => 0];
        }

        $videos = $this->parseItems($xpath, "//a[contains(@class,'stui-vodlist__thumb')]");
        
        return [
            'list' => $videos, 
            'page' => intval($pg), 
            'pagecount' => 1, 
            'limit' => 20, 
            'total' => count($videos)
        ];
    }

    public function getPlayerContent($id) {
        if (strpos($id, 'http') === 0) {
            return ['parse' => 0, 'playUrl' => '', 'url' => $id, 'header' => ['Referer' => $this->base]];
        }

        $url = $this->base . $id;
        $html = $this->request($url);
        
        if ($html) {
            // 查找API
            if (preg_match('/http:\/\/api\.yongfan99\.com:81\/content\.php\?[^\'\"]+/', $html, $m)) {
                $api = $this->request($m[0]);
                if ($api && preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $api, $m3u8)) {
                    return ['parse' => 0, 'playUrl' => '', 'url' => $m3u8[0], 'header' => ['Referer' => $url]];
                }
            }
            
            // 查找iframe
            if (preg_match_all('/<iframe[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
                foreach ($m[1] as $iframe) {
                    if (!preg_match('/^http/', $iframe)) $iframe = $this->base . $iframe;
                    $iframe_html = $this->request($iframe);
                    if ($iframe_html && preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $iframe_html, $m3u8)) {
                        return ['parse' => 0, 'playUrl' => '', 'url' => $m3u8[0], 'header' => ['Referer' => $iframe]];
                    }
                }
            }
            
            // 直接搜索m3u8
            if (preg_match('/https?:\/\/[^\s"\']+\.m3u8[^\s"\']*/', $html, $m3u8)) {
                return ['parse' => 0, 'playUrl' => '', 'url' => $m3u8[0], 'header' => ['Referer' => $url]];
            }
        }
        
        return ['parse' => 1, 'playUrl' => '', 'url' => $url, 'header' => ['Referer' => $this->base]];
    }

    public function __destruct() {
        if ($this->ch) curl_close($this->ch);
    }
}

function getHome() {
    return (new QiYouSpider())->getHomeContent();
}

function getCategory($tid, $page, $by = 'time') {
    return (new QiYouSpider())->getCategoryContent($tid, $page, $by);
}

function getDetail($ids) {
    return (new QiYouSpider())->getDetailContent($ids);
}

function search($keyword, $page) {
    return (new QiYouSpider())->getSearchContent($keyword, $page);
}

function getPlay($flag, $id) {
    return (new QiYouSpider())->getPlayerContent($id);
}
?>