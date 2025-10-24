<?php
/**
 * TVBox PHP 爬虫脚本 - 139FM有声小说版
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

class FM139Spider {
    private $base = "https://139fm.cyou";
    private $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: zh-CN,zh;q=0.9,zh-TW;q=0.8',
        'Cache-Control: max-age=0',
        'Connection: keep-alive',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-User: ?1',
        'Sec-Fetch-Dest: document',
        'Upgrade-Insecure-Requests: 1'
    ];
    
    private $category_map = [
        "1" => "长篇有声",
        "2" => "短篇有声", 
        "3" => "自慰催眠",
        "4" => "ASMR专区"
    ];
    
    private $anchor_map = [
        "小苮儿" => "小苮儿",
        "步非烟团队" => "步非烟团队",
        "小野猫" => "小野猫",
        "戴逸" => "戴逸",
        "姽狐" => "姽狐",
        "小咪" => "小咪",
        "浅浅" => "浅浅",
        "季姜" => "季姜",
        "丽莎" => "丽莎",
        "雅朵" => "雅朵",
        "曼曼" => "曼曼",
        "小窈" => "小窈",
        "ASMR专区" => "ASMR专区"
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
            CURLOPT_HTTPHEADER => $this->headers
        ]);
        return $ch;
    }

    public function fetch($url) {
        curl_setopt($this->session, CURLOPT_URL, $url);
        $response = curl_exec($this->session);
        $httpCode = curl_getinfo($this->session, CURLINFO_HTTP_CODE);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }

    private function rot13_char($char) {
        if ($char >= 'a' && $char <= 'z') {
            return chr(((ord($char) - ord('a') + 13) % 26) + ord('a'));
        } elseif ($char >= 'A' && $char <= 'Z') {
            return chr(((ord($char) - ord('A') + 13) % 26) + ord('A'));
        } else {
            return $char;
        }
    }

    private function ee2($text) {
        $result = '';
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            if (($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z')) {
                $result .= $this->rot13_char($char);
            } else {
                $result .= $char;
            }
        }
        return $result;
    }

    private function dd0($encrypted_text, $default_value = '') {
        try {
            $step1 = $this->ee2($encrypted_text);
            $step2 = base64_decode($step1);
            if ($step2 === false) {
                return $default_value;
            }
            $step3 = $this->ee2($step2);
            return $step3;
        } catch (Exception $e) {
            return $default_value;
        }
    }

    private function extract_conf_from_html($html) {
        if (strpos($html, 'var _conf') === false && strpos($html, 'var _conf') === false) {
            return null;
        }
        
        $patterns = [
            "/var\s+_conf\s*=\s*\{\s*a\s*:\s*\[((?:'[^']*'\s*,?\s*)*)\]/",
            '/var\s+_conf\s*=\s*\{\s*a\s*:\s*\[((?:"[^"]*"\s*,?\s*)*)\]/',
            "/_conf\s*=\s*\{\s*a\s*:\s*\[((?:'[^']*'\s*,?\s*)*)\]/",
            '/_conf\s*=\s*\{\s*a\s*:\s*\[((?:"[^"]*"\s*,?\s*)*)\]/',
            "/a\s*:\s*\[((?:'[^']*'\s*,?\s*)*)\]/",
            '/a\s*:\s*\[((?:"[^"]*"\s*,?\s*)*)\]/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $array_content = $matches[1];
                
                $strings = [];
                if (preg_match_all("/'([^']*)'/", $array_content, $string_matches)) {
                    $strings = $string_matches[1];
                } elseif (preg_match_all('/"([^"]*)"/', $array_content, $string_matches)) {
                    $strings = $string_matches[1];
                }
                
                if (!empty($strings)) {
                    return ['a' => $strings, 'c' => ''];
                }
            }
        }
        
        return null;
    }

    private function decrypt_all($conf_data) {
        $results = [];
        if (isset($conf_data['a']) && is_array($conf_data['a'])) {
            foreach ($conf_data['a'] as $encrypted_str) {
                if (!empty($encrypted_str)) {
                    $result = $this->dd0($encrypted_str, $conf_data['c'] ?? '');
                    $results[] = $result;
                }
            }
        }
        return $results;
    }

    private function removeHtmlTags($text) {
        return strip_tags($text);
    }

    public function getHomeContent() {
        try {
            $url = $this->base . "/podcasts";
            $r = $this->fetch($url);
            if (!$r) {
                return ['class' => [], 'list' => []];
            }
            
            $categories = [];
            
            // 解析分类
            if (preg_match('/<dl[^>]*id="areas"[^>]*>(.*?)<\/dl>/s', $r, $areas_match)) {
                $areas_html = $areas_match[1];
                if (preg_match_all('/<dd[^>]*data-val="([^"]*)"[^>]*>(.*?)<\/dd>/s', $areas_html, $dd_matches, PREG_SET_ORDER)) {
                    foreach ($dd_matches as $match) {
                        $data_val = $match[1];
                        $text = strip_tags($match[2]);
                        if ($data_val && $data_val != '-1') {
                            $categories[] = [
                                'type_id' => $data_val,
                                'type_name' => trim($text)
                            ];
                        }
                    }
                }
            }
            
            // 解析主播分类
            if (preg_match('/<dl[^>]*id="tags"[^>]*>(.*?)<\/dl>/s', $r, $tags_match)) {
                $tags_html = $tags_match[1];
                if (preg_match_all('/<dd[^>]*data-val="([^"]*)"[^>]*>(.*?)<\/dd>/s', $tags_html, $dd_matches, PREG_SET_ORDER)) {
                    foreach ($dd_matches as $match) {
                        $data_val = $match[1];
                        if ($data_val && $data_val != '全部' && isset($this->anchor_map[$data_val])) {
                            $categories[] = [
                                'type_id' => 'anchor_' . $data_val,
                                'type_name' => '主播-' . $data_val
                            ];
                        }
                    }
                }
            }
            
            // 获取首页音频列表
            $audios = [];
            if (preg_match_all('/<div[^>]*class="mh-item"[^>]*>(.*?)<\/div>/s', $r, $item_matches, PREG_SET_ORDER)) {
                foreach ($item_matches as $item_match) {
                    $item_html = $item_match[1];
                    
                    // 提取链接
                    if (!preg_match('/<a[^>]*href="([^"]*)"[^>]*>/', $item_html, $href_match)) {
                        continue;
                    }
                    $href = $href_match[1];
                    
                    // 提取封面
                    $cover_url = '';
                    if (preg_match('/<p[^>]*class="mh-cover"[^>]*style="[^"]*url\(([^)]*)\)[^"]*"[^>]*>/', $item_html, $cover_match)) {
                        $cover_url = trim($cover_match[1], " '\"");
                    }
                    
                    // 提取标题
                    $title = '';
                    if (preg_match('/<h2[^>]*class="title"[^>]*>.*?<a[^>]*>(.*?)<\/a>.*?<\/h2>/s', $item_html, $title_match)) {
                        $title = strip_tags($title_match[1]);
                    }
                    
                    // 提取章节信息
                    $chapter = '';
                    if (preg_match('/<p[^>]*class="chapter"[^>]*>(.*?)<\/p>/s', $item_html, $chapter_match)) {
                        $chapter = strip_tags($chapter_match[1]);
                    }
                    
                    if ($href && $title) {
                        $parts = explode('/', $href);
                        $vod_id = end($parts);
                        $audios[] = [
                            'vod_id' => $vod_id,
                            'vod_name' => trim($title),
                            'vod_pic' => $cover_url,
                            'vod_remarks' => $chapter ? trim($chapter) : '暂无简介'
                        ];
                    }
                }
            }
            
            return [
                'class' => $categories,
                'list' => $audios
            ];
        } catch (Exception $e) {
            return ['class' => [], 'list' => []];
        }
    }

    public function getCategoryContent($tid, $pg) {
        try {
            $url = $this->base . "/podcasts";
            $params = [];
            
            if ($tid && strpos($tid, "anchor_") === 0) {
                $anchor = str_replace("anchor_", "", $tid);
                $params['tag'] = $anchor;
            } elseif ($tid && isset($this->category_map[$tid])) {
                $params['area'] = $tid;
            }
            
            if ($pg && intval($pg) > 1) {
                $params['page'] = $pg;
            }
            
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
            
            $r = $this->fetch($url);
            if (!$r) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'limit' => 48,
                    'total' => 0
                ];
            }
            
            $audios = [];
            if (preg_match_all('/<div[^>]*class="mh-item"[^>]*>(.*?)<\/div>/s', $r, $item_matches, PREG_SET_ORDER)) {
                foreach ($item_matches as $item_match) {
                    $item_html = $item_match[1];
                    
                    // 提取链接
                    if (!preg_match('/<a[^>]*href="([^"]*)"[^>]*>/', $item_html, $href_match)) {
                        continue;
                    }
                    $href = $href_match[1];
                    
                    // 提取封面
                    $cover_url = '';
                    if (preg_match('/<p[^>]*class="mh-cover"[^>]*style="[^"]*url\(([^)]*)\)[^"]*"[^>]*>/', $item_html, $cover_match)) {
                        $cover_url = trim($cover_match[1], " '\"");
                    }
                    
                    // 提取标题
                    $title = '';
                    if (preg_match('/<h2[^>]*class="title"[^>]*>.*?<a[^>]*>(.*?)<\/a>.*?<\/h2>/s', $item_html, $title_match)) {
                        $title = strip_tags($title_match[1]);
                    }
                    
                    // 提取章节信息
                    $chapter = '';
                    if (preg_match('/<p[^>]*class="chapter"[^>]*>(.*?)<\/p>/s', $item_html, $chapter_match)) {
                        $chapter = strip_tags($chapter_match[1]);
                    }
                    
                    if ($href && $title) {
                        $parts = explode('/', $href);
                        $vod_id = end($parts);
                        $audios[] = [
                            'vod_id' => $vod_id,
                            'vod_name' => trim($title),
                            'vod_pic' => $cover_url,
                            'vod_remarks' => $chapter ? trim($chapter) : '暂无简介'
                        ];
                    }
                }
            }
            
            // 解析分页信息
            $pagecount = 1;
            if (preg_match('/<div[^>]*class="pagination"[^>]*>(.*?)<\/div>/s', $r, $pagination_match)) {
                $pagination_html = $pagination_match[1];
                if (preg_match_all('/<a[^>]*href="[^"]*page=(\d+)[^"]*"[^>]*>/', $pagination_html, $page_matches)) {
                    foreach ($page_matches[1] as $page_num) {
                        $pagecount = max($pagecount, intval($page_num));
                    }
                }
            }
            
            return [
                'list' => $audios,
                'page' => intval($pg),
                'pagecount' => $pagecount,
                'limit' => 48,
                'total' => count($audios) * $pagecount
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'limit' => 48,
                'total' => 0
            ];
        }
    }

    public function getDetailContent($ids) {
        $result = ['list' => []];
        
        foreach ($ids as $id) {
            try {
                $url = $this->base . "/podcast/" . $id;
                $r = $this->fetch($url);
                if (!$r) {
                    $result['list'][] = [
                        'vod_id' => $id,
                        'vod_name' => '获取失败',
                        'vod_pic' => '',
                        'vod_content' => '网络请求失败'
                    ];
                    continue;
                }
                
                // 提取_conf对象并解密音频URL
                $_conf = $this->extract_conf_from_html($r);
                $decrypted_urls = [];
                
                if ($_conf) {
                    $decrypted_urls = $this->decrypt_all($_conf);
                }
                
                // 基本信息
                $title = '';
                if (preg_match('/<title>(.*?)<\/title>/', $r, $title_match)) {
                    $title = str_replace('-139FM', '', $title_match[1]);
                }
                if (!$title) {
                    $title = '音频_' . $id;
                }
                
                // 获取封面
                $cover_url = '';
                if (preg_match('/<img[^>]*data-amplitude-song-info="cover_art_url"[^>]*src="([^"]*)"[^>]*>/', $r, $cover_match)) {
                    $cover_url = $cover_match[1];
                }
                
                if (!$cover_url && preg_match('/<div[^>]*class="mh-cover"[^>]*style="[^"]*url\(([^)]*)\)[^"]*"[^>]*>/', $r, $cover_match)) {
                    $cover_url = trim($cover_match[1], " '\"");
                }
                
                // 解析播放列表
                $episodes = [];
                if (preg_match_all('/<div[^>]*class="song"[^>]*>(.*?)<\/div>/s', $r, $song_matches, PREG_SET_ORDER)) {
                    foreach ($song_matches as $index => $song_match) {
                        $song_html = $song_match[1];
                        
                        $episode_title = '';
                        if (preg_match('/<div[^>]*class="song-title"[^>]*>(.*?)<\/div>/s', $song_html, $title_match)) {
                            $episode_title = strip_tags($title_match[1]);
                        }
                        if (!$episode_title) {
                            $episode_title = '第' . ($index + 1) . '集';
                        }
                        
                        $episode_artist = '';
                        if (preg_match('/<div[^>]*class="song-artist"[^>]*>(.*?)<\/div>/s', $song_html, $artist_match)) {
                            $episode_artist = strip_tags($artist_match[1]);
                        }
                        
                        $require_buy = strpos($song_html, 'data-require-buy="1"') !== false;
                        
                        $chapter_id = '';
                        if (preg_match('/data-chapter-id="([^"]*)"/', $song_html, $chapter_match)) {
                            $chapter_id = $chapter_match[1];
                        }
                        
                        $audio_url = isset($decrypted_urls[$index]) ? $decrypted_urls[$index] : '';
                        
                        $episodes[] = [
                            'name' => $episode_title,
                            'artist' => $episode_artist,
                            'requireBuy' => $require_buy,
                            'chapterId' => $chapter_id,
                            'url' => $audio_url
                        ];
                    }
                }
                
                // 解析详情信息
                $vod_content = '暂无简介';
                if (preg_match('/"desc":\s*"([^"]*)"/', $r, $desc_match)) {
                    $vod_content = str_replace('简介：', '', $desc_match[1]);
                }
                
                $vod_remarks = '';
                if (preg_match('/"clicks":\s*"([^"]*)"/', $r, $clicks_match)) {
                    $vod_remarks = str_replace('热度：', '热度:', $clicks_match[1]);
                }
                
                $type_name = '';
                if (preg_match('/"area":\s*"([^"]*)"/', $r, $area_match)) {
                    $type_name = $this->removeHtmlTags($area_match[1]);
                    $type_name = str_replace('类型：', '', $type_name);
                }
                
                $vod_actor = '';
                if (preg_match('/"tag":\s*"([^"]*)"/', $r, $tag_match)) {
                    $vod_actor = $this->removeHtmlTags($tag_match[1]);
                    $vod_actor = str_replace('主播：', '', $vod_actor);
                }
                
                // 构建播放源
                $play_from = '139FM';
                
                // 构建播放URL
                $play_url_parts = [];
                foreach ($episodes as $index => $ep) {
                    $episode_name = $ep['name'];
                    if ($ep['requireBuy']) {
                        $episode_name .= '[付费]';
                    }
                    
                    $episode_url = $ep['url'];
                    if (!$episode_url) {
                        $episode_url = $id . '_' . $ep['chapterId'] . '_' . $index;
                    }
                    
                    $play_url_parts[] = $episode_name . '$' . $episode_url;
                }
                
                $play_url = implode('#', $play_url_parts);
                
                $result['list'][] = [
                    'vod_id' => $id,
                    'vod_name' => str_replace('全集免费高清无修在线阅读', '', $title),
                    'vod_pic' => $cover_url,
                    'type_name' => $type_name,
                    'vod_actor' => $vod_actor,
                    'vod_director' => $episodes ? '共' . count($episodes) . '集' : '',
                    'vod_content' => $vod_content,
                    'vod_remarks' => $vod_remarks,
                    'vod_play_from' => $play_from,
                    'vod_play_url' => $play_url
                ];
                
            } catch (Exception $e) {
                $result['list'][] = [
                    'vod_id' => $id,
                    'vod_name' => '获取失败',
                    'vod_pic' => '',
                    'vod_content' => '获取详情失败: ' . $e->getMessage()
                ];
            }
        }
        
        return $result;
    }

    public function getSearchContent($key, $pg = 1) {
        try {
            $params = ['keyword' => $key];
            if ($pg && intval($pg) > 1) {
                $params['page'] = $pg;
            }
            
            $url = $this->base . "/search?" . http_build_query($params);
            $r = $this->fetch($url);
            if (!$r) {
                return [
                    'list' => [],
                    'page' => intval($pg),
                    'pagecount' => 1,
                    'total' => 0
                ];
            }
            
            $audios = [];
            if (preg_match_all('/<div[^>]*class="mh-item"[^>]*>(.*?)<\/div>/s', $r, $item_matches, PREG_SET_ORDER)) {
                foreach ($item_matches as $item_match) {
                    $item_html = $item_match[1];
                    
                    // 提取链接
                    if (!preg_match('/<a[^>]*href="([^"]*)"[^>]*>/', $item_html, $href_match)) {
                        continue;
                    }
                    $href = $href_match[1];
                    
                    // 提取封面
                    $cover_url = '';
                    if (preg_match('/<p[^>]*class="mh-cover"[^>]*style="[^"]*url\(([^)]*)\)[^"]*"[^>]*>/', $item_html, $cover_match)) {
                        $cover_url = trim($cover_match[1], " '\"");
                    }
                    
                    // 提取标题
                    $title = '';
                    if (preg_match('/<h2[^>]*class="title"[^>]*>.*?<a[^>]*>(.*?)<\/a>.*?<\/h2>/s', $item_html, $title_match)) {
                        $title = strip_tags($title_match[1]);
                    }
                    
                    // 提取章节信息
                    $chapter = '';
                    if (preg_match('/<p[^>]*class="chapter"[^>]*>(.*?)<\/p>/s', $item_html, $chapter_match)) {
                        $chapter = strip_tags($chapter_match[1]);
                    }
                    
                    if ($href && $title) {
                        $parts = explode('/', $href);
                        $vod_id = end($parts);
                        $audios[] = [
                            'vod_id' => $vod_id,
                            'vod_name' => trim($title),
                            'vod_pic' => $cover_url,
                            'vod_remarks' => $chapter ? trim($chapter) : '暂无简介'
                        ];
                    }
                }
            }
            
            return [
                'list' => $audios,
                'page' => intval($pg),
                'pagecount' => 1,
                'total' => count($audios)
            ];
        } catch (Exception $e) {
            return [
                'list' => [],
                'page' => intval($pg),
                'pagecount' => 1,
                'total' => 0
            ];
        }
    }

    public function getPlayerContent($id) {
        try {
            // 如果id已经是完整的URL，直接使用
            if (strpos($id, 'http') === 0) {
                return [
                    'parse' => 0,
                    'playUrl' => '',
                    'url' => $id,
                    'header' => [
                        'Referer' => $this->base,
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                        'Accept' => '*/*',
                        'Range' => 'bytes=0-'
                    ]
                ];
            }
            
            // id格式: podcastId_chapterId_index
            $parts = explode('_', $id);
            if (count($parts) >= 3) {
                $podcast_id = $parts[0];
                $chapter_id = $parts[1];
                $index = $parts[2];
                
                // 获取详情页面来解密音频URL
                $url = $this->base . "/podcast/" . $podcast_id;
                $r = $this->fetch($url);
                if ($r) {
                    $_conf = $this->extract_conf_from_html($r);
                    if ($_conf) {
                        $decrypted_urls = $this->decrypt_all($_conf);
                        $audio_index = intval($index);
                        
                        if (isset($decrypted_urls[$audio_index]) && $decrypted_urls[$audio_index]) {
                            return [
                                'parse' => 0,
                                'playUrl' => '',
                                'url' => $decrypted_urls[$audio_index],
                                'header' => [
                                    'Referer' => $this->base . '/podcast/' . $podcast_id,
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
                                    'Accept' => '*/*',
                                    'Range' => 'bytes=0-'
                                ]
                            ];
                        }
                    }
                }
            }
            
            return [
                'parse' => 0,
                'playUrl' => '',
                'url' => '',
                'header' => []
            ];
            
        } catch (Exception $e) {
            return [
                'parse' => 0,
                'playUrl' => '',
                'url' => '',
                'header' => []
            ];
        }
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
    $spider = new FM139Spider();
    return $spider->getHomeContent();
}

/**
 * 分类列表
 */
function getCategory($tid, $page) {
    $spider = new FM139Spider();
    return $spider->getCategoryContent($tid, $page);
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $spider = new FM139Spider();
    return $spider->getDetailContent(explode(',', $ids));
}

/**
 * 搜索
 */
function search($keyword, $page) {
    $spider = new FM139Spider();
    return $spider->getSearchContent($keyword, $page);
}

/**
 * 获取播放地址
 */
function getPlay($flag, $id) {
    $spider = new FM139Spider();
    return $spider->getPlayerContent($id);
}
?>