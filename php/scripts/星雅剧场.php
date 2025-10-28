<?php
// 星芽剧场爬虫 - 严格数据结构
header('Content-Type: application/json; charset=utf-8');

// 配置
$xurl = "https://app.whjzjx.cn";
$cache_dir = __DIR__ . '/cache/';
$cache_ttl = 300;

// 创建缓存目录
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// 获取Authorization
function get_authorization() {
    $times = round(microtime(true) * 1000);
    
    $data = [
        "device" => "2a50580e69d38388c94c93605241fb306",
        "package_name" => "com.jz.xydj",
        "android_id" => "ec1280db12795506",
        "install_first_open" => true,
        "first_install_time" => 1752505243345,
        "last_update_time" => 1752505243345,
        "report_link_url" => "",
        "authorization" => "",
        "timestamp" => $times
    ];
    
    $plain_text = json_encode($data, JSON_UNESCAPED_UNICODE);
    $key = "B@ecf920Od8A4df7";
    
    $ciphertext = openssl_encrypt(
        $plain_text,
        'AES-128-ECB',
        $key,
        OPENSSL_RAW_DATA,
        ''
    );
    
    $encrypted = base64_encode($ciphertext);
    
    $headerf = [
        "platform: 1",
        "user-agent: Mozilla/5.0 (Linux; Android 9; V1938T Build/PQ3A.190705.08211809; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/91.0.4472.114 Safari/537.36",
        "content-type: application/json; charset=utf-8"
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://u.shytkjgs.com/user/v3/account/login",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encrypted,
        CURLOPT_HTTPHEADER => $headerf,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15, // 增加超时时间以适应网络延迟
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error || $http_code != 200) {
        error_log("Login request failed: HTTP {$http_code}, Error: {$error}");
        return '';
    }
    
    if ($response) {
        $response_data = json_decode($response, true);
        return $response_data['data']['token'] ?? '';
    }
    
    return '';
}

// 发送API请求
function http_request($url, $post_data = null) {
    static $authorization = null;
    static $retry_count = 0;
    const MAX_RETRIES = 2;

    if ($authorization === null) {
        $authorization = get_authorization();
        if (empty($authorization)) {
            error_log("Failed to obtain authorization token after retries");
            return null;
        }
    }
    
    $headers = [
        'authorization: ' . $authorization,
        'platform: 1',
        'version_name: 3.8.3.1',
        'user-agent: Mozilla/5.0 (Linux; Android 12; Pixel 3 XL) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.101 Mobile Safari/537.36'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers
    ]);
    
    if ($post_data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        $headers[] = 'content-type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error || $http_code != 200) {
        error_log("API request failed: URL {$url}, HTTP {$http_code}, Error: {$error}");
        if ($retry_count < MAX_RETRIES) {
            $retry_count++;
            sleep(2); // 等待2秒后重试
            return http_request($url, $post_data); // 重试
        }
        return null;
    }
    
    if ($response) {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode failed for URL {$url}: " . json_last_error_msg());
            return null;
        }
        return $data;
    }
    
    return null;
}

// 参数解析
$ac = $_GET['ac'] ?? 'detail';
$t = $_GET['t'] ?? '';
$pg = $_GET['pg'] ?? '1';
$f = $_GET['f'] ?? '';
$ids = $_GET['ids'] ?? '';
$wd = $_GET['wd'] ?? '';
$flag = $_GET['flag'] ?? '';
$id = $_GET['id'] ?? '';

// 主逻辑 - 严格数据结构
switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            $did = $ids;
            $data = http_request("{$xurl}/v2/theater_parent/detail?theater_parent_id={$did}");
            
            if (!$data || empty($data['data'])) {
                $result = ['list' => [['vod_id' => $did, 'vod_name' => '数据获取失败', 'vod_play_from' => '无', 'vod_play_url' => '1$无有效播放地址']]];
            } else {
                $content = '剧情：' . ($data['data']['introduction'] ?? '');
                $area = $data['data']['desc_tags'][0] ?? '';
                $remarks = $data['data']['filing'] ?? '';
                
                $xianlu = '星芽';
                $bofang = '';
                
                if (!empty($data['data']['theaters'])) {
                    foreach ($data['data']['theaters'] as $sou) {
                        if (isset($sou['num']) && isset($sou['son_video_url']) && filter_var($sou['son_video_url'], FILTER_VALIDATE_URL)) {
                            $bofang .= $sou['num'] . '$' . $sou['son_video_url'] . '#';
                        }
                    }
                    $bofang = rtrim($bofang, '#');
                } elseif (!empty($data['data']['video_url']) && filter_var($data['data']['video_url'], FILTER_VALIDATE_URL)) {
                    $bofang = '1$' . $data['data']['video_url'];
                } else {
                    $bofang = '1$无有效播放地址';
                    $xianlu = '无';
                }
                
                $video = [
                    'vod_id' => $did,
                    'vod_name' => $data['data']['title'] ?? '',
                    'vod_pic' => $data['data']['cover_url'] ?? '',
                    'vod_content' => $content,
                    'vod_area' => $area,
                    'vod_remarks' => $remarks,
                    'vod_play_from' => $xianlu,
                    'vod_play_url' => $bofang
                ];
                
                $result = ['list' => [$video]];
            }
        } elseif (!empty($t)) {
            $page = max(1, intval($pg));
            $data = http_request("{$xurl}/v1/theater/home_page?theater_class_id={$t}&page_num={$page}&page_size=24");
            
            $videos = [];
            if ($data && !empty($data['data']['list'])) {
                foreach ($data['data']['list'] as $vod) {
                    $theater = $vod['theater'];
                    $videos[] = [
                        'vod_id' => $theater['id'],
                        'vod_name' => $theater['title'],
                        'vod_pic' => $theater['cover_url'],
                        'vod_remarks' => $theater['theme'] ?? $theater['play_amount_str'] ?? ''
                    ];
                }
            } else {
                $videos[] = ['vod_id' => 0, 'vod_name' => '数据获取失败', 'vod_pic' => '', 'vod_remarks' => ''];
            }
            
            $result = [
                'list' => $videos,
                'page' => $page,
                'pagecount' => 9999,
                'limit' => 90,
                'total' => 999999
            ];
        } else {
            $result = [
                'class' => [
                    ['type_id' => '1', 'type_name' => '剧场'],
                    ['type_id' => '3', 'type_name' => '新剧'],
                    ['type_id' => '2', 'type_name' => '热播'],
                    ['type_id' => '7', 'type_name' => '星选'],
                    ['type_id' => '5', 'type_name' => '阳光']
                ]
            ];
        }
        break;
    
    case 'search':
        $page = max(1, intval($pg));
        $key = $wd;
        
        $payload = ["text" => $key];
        $data = http_request("{$xurl}/v3/search", $payload);
        
        $videos = [];
        if ($data && !empty($data['data']['theater']['search_data'])) {
            foreach ($data['data']['theater']['search_data'] as $vod) {
                $videos[] = [
                    'vod_id' => $vod['id'],
                    'vod_name' => $vod['title'],
                    'vod_pic' => $vod['cover_url'],
                    'vod_remarks' => $vod['score_str'] ?? ''
                ];
            }
        } else {
            $videos[] = ['vod_id' => 0, 'vod_name' => '搜索结果为空', 'vod_pic' => '', 'vod_remarks' => ''];
        }
        
        $result = [
            'list' => $videos,
            'page' => $page,
            'pagecount' => 9999,
            'limit' => 90,
            'total' => 999999
        ];
        break;
    
    case 'play':
        $result = ['list' => []];
        $detail_data = http_request("{$xurl}/v2/theater_parent/detail?theater_parent_id={$id}");
        
        if ($detail_data && !empty($detail_data['data'])) {
            $video_data = $detail_data['data'];
            $xianlu = '星芽';
            $bofang = '';

            if (!empty($video_data['theaters'])) {
                foreach ($video_data['theaters'] as $sou) {
                    if (isset($sou['num']) && isset($sou['son_video_url']) && filter_var($sou['son_video_url'], FILTER_VALIDATE_URL)) {
                        $bofang .= $sou['num'] . '$' . $sou['son_video_url'] . '#';
                    }
                }
                $bofang = rtrim($bofang, '#');
            } elseif (!empty($video_data['video_url']) && filter_var($video_data['video_url'], FILTER_VALIDATE_URL)) {
                $bofang = '1$' . $video_data['video_url'];
            } else {
                $bofang = '1$无有效播放地址';
                $xianlu = '无';
            }

            $video = [
                'vod_id' => $id,
                'vod_name' => $video_data['title'] ?? '未知视频',
                'vod_pic' => $video_data['cover_url'] ?? '',
                'vod_content' => '剧情：' . ($video_data['introduction'] ?? '暂无剧情'),
                'vod_area' => $video_data['desc_tags'][0] ?? '未知地区',
                'vod_remarks' => $video_data['filing'] ?? '暂无备注',
                'vod_play_from' => $xianlu,
                'vod_play_url' => $bofang
            ];
            $result['list'] = [$video];
        } else {
            $result['list'] = [
                [
                    'vod_id' => $id,
                    'vod_name' => '获取失败',
                    'vod_play_from' => '无',
                    'vod_play_url' => '1$获取视频数据失败，请检查网络或联系管理员'
                ]
            ];
        }
        break;
    
    default:
        $result = ['error' => '未知操作: ' . $ac];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>