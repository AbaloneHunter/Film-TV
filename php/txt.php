<?php

setlocale(LC_ALL, 'en_US.utf8');
// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// --- 文件系统配置 ---
define('WJ_DIR', 'txt');
// 定义存放分类文件的目录

/**
 * 辅助函数：从指定文件的内容行中，为特定分类查找其对应的内容。
 * @param array $lines 文件所有内容行。
 * @param string $target_category 目标分类的名称。
 * @return array 属于该分类的内容行数组。
 */
function find_content_for_category($lines, $target_category) {
    // 首先检查文件内是否存在任何#genre#标记
    $has_genre_marker = false;
    foreach ($lines as $line) {
        if (strpos(trim($line), ',#genre#') !== false) {
            $has_genre_marker = true;
            break;
        }
    }

    // 如果文件内没有#genre#标记，则所有行都属于该文件代表的分类
    if (!$has_genre_marker) {
        return $lines;
    }

    // 如果文件内有#genre#标记，则精确查找对应分区
    $content_to_return = [];
    $collecting = false;
    foreach ($lines as $line) {
        $trimmed_line = trim($line);
        // 检查当前行是否是#genre#标记行
        if (strpos($trimmed_line, ',#genre#') !== false) {
            if ($collecting) { // 如果之前正在收集，说明遇到了下一个分类，停止
                break;
            }
            $line_category = trim(explode(',', $trimmed_line, 2)[0]);
            if (strcasecmp($line_category, $target_category) == 0) { // 如果此行的分类名匹配（忽略大小写），则开始收集
                $collecting = true;
            }
        } elseif ($collecting) { // 如果不是标记行且处于收集中，则加入结果
            $content_to_return[] = $line;
        }
    }
    return $content_to_return;
}

// --- API 路由逻辑 ---
$ac = $_GET['ac'] ?? '';
$ids = $_GET['ids'] ?? '';
$wd = $_GET['wd'] ?? '';
$flag = $_GET['flag'] ?? '';
$play = $_GET['play'] ?? '';
$t = $_GET['t'] ?? '';
$filter = $_GET['filter'] ?? '';
$pg = $_GET['pg'] ?? '1';


if (!empty($wd)) {
    $text_files = glob(WJ_DIR . '/*.txt');
    $found_items = []; // 存储所有匹配的项目
    
    // 使用关键词作为搜索模式
    $search_pattern = $wd;
    
    foreach ($text_files as $file) {
        // 读取文件内容，按行分割
        $content = file_get_contents($file);
        if ($content === false) {
            continue; // 文件读取失败，跳到下一个文件
        }
        
        // 获取文件basename（不含扩展名）
        $file_basename = pathinfo($file, PATHINFO_FILENAME);
        
        // 按行分割文件内容
        $lines = explode("\n", $content);
        
        foreach ($lines as $line_index => $line) {
            $line = trim($line); // 去除首尾空白
            if (empty($line)) {
                continue; // 跳过空行
            }
            
            // 检查这行是否包含 http 和搜索关键词
            if (strpos($line, 'http') !== false && stripos($line, $search_pattern) !== false) {
                // 解析行内容，格式为: 标题,URL
                $parts = explode(',', $line, 2); // 只分割成两部分，防止URL中的逗号被误分割
                
                if (count($parts) == 2) {
                    $vod_name = trim($parts[0]);
                    $vod_play_url = trim($parts[1]);
                    
                    // 查找当前行上方最近的包含 #genre# 的行
                    $genre_line = '';
                    for ($i = $line_index - 1; $i >= 0; $i--) {
                        $check_line = trim($lines[$i]);
                        if (strpos($check_line, '#genre#') !== false) {
                            $genre_line = $check_line;
                            break;
                        }
                    }
                    
                    // 生成 vod_id: 文件basename + | + genre行的base64编码
                    $vod_id_content = $file_basename . '|' . $genre_line;
                    $vod_id = base64_encode($vod_id_content);
                    
                    // 按指定格式创建item
                    $item = [
                        'vod_id' => $vod_id,
                        'vod_name' => $vod_name,
       //                 'vod_pic' => "https://www.252035.xyz/imgs?t=". mt_rand(),
                        'vod_actor' => '', // 空白
                        'vod_area' => '', // 空白
                        'vod_director' => '', // 空白
                        'vod_content' => '', // 空白
                        'vod_play_from' => '文件', // 空白
                        'vod_play_url' => $vod_play_url
                    ];
                    
                    $found_items[] = $item;
                }
            }
        }
    }
    
    // 输出结果
    if (!empty($found_items)) {
        // 找到了匹配的项目，按标准格式封装响应
        $response = ['list' => $found_items];
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        // 如果遍历完所有文件都没找到
        // http_response_code(404);
        echo json_encode(['error' => 'No items found for keyword: ' . $search_pattern], JSON_UNESCAPED_UNICODE);
    }
    exit; // 处理完搜索请求后必须终止脚本
}
// 1. 处理详情请求 (ac=detail) - 已优化
if ($ac === 'detail' && !empty($ids)) {

    // 解码ID
    $decoded_id = base64_decode($ids);
    // 分离出 "文件名|分类名" 和 ",#genre"
    list($id_part,) = explode(',', $decoded_id, 2);
    // 分离出 文件名 和 分类名
    $parts = explode('|', $id_part, 2);

    $file_basename = $parts[0] ?? '';
    $target_category = trim($parts[1] ?? $file_basename); // 如果没有分类名，则默认为文件名

    $play_urls = [];
    $file_path = WJ_DIR . '/' . $file_basename . '.txt';

    // 直接定位并打开唯一对应的文件，不再遍历目录
    if (file_exists($file_path)) {
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 检查文件内是否存在#genre#标记
        $has_genre_marker_in_file = false;
        foreach ($lines as $line) {
            if (strpos(trim($line), ',#genre#') !== false) {
                $has_genre_marker_in_file = true;
                break;
            }
        }

        // 根据是否有#genre#标记来确定目标分类
        if (!$has_genre_marker_in_file) {
            // 如果没有#genre#标记，则目标分类强制为 '默认'
            $target_category = '默认';
        }

        // 使用辅助函数从文件内容中提取所需行
        $content_lines = find_content_for_category($lines, $target_category);
        if (!empty($content_lines)) {
            $count = 1;
            foreach ($content_lines as $line) {
                $line_parts = explode(',', $line, 2);
                if (count($line_parts) === 2) {
                    $name = trim($line_parts[0]);
                    $url = trim($line_parts[1]);
                    if ($url && $url !== '#genre#') {
                        $play_urls[] = $count . '、' . $name . '$' . base64_encode($url);
                        $count++;
                    }
                }
            }
        }
    }

    $response = [
        'list' => [
            [
                'vod_id' => $ids,
                'vod_name' => $target_category,
     //           'vod_pic' => "https://www.252035.xyz/imgs?t=". mt_rand(),
                'vod_actor' => $target_category,
                'vod_content' => "来自 '" . htmlspecialchars($target_category) . "' 分类的内容。",
                'vod_director' => '',
                'vod_remarks' => '',
                'vod_play_from' => $target_category,
                'vod_play_url' => implode('#', $play_urls),
                'parse' => 0,
                'jx' => 0,
                'file_name' => $file_basename
            ]
        ],
        'page' => 1,
        'pagecount' => 1,
        'limit' => 0,
        'total' => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// 2. 处理播放请求
elseif (!empty($flag) && !empty($play)) {
    $final_url = base64_decode($play);
    $response = [
        'header' => '', 'parse' => '0', 'playUrl' => '', 'url' => $final_url
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// 3. 根据 #genre# 行生成分类列表 (当URL中含有t参数时触发) - 已优化

// 初始化响应数组，默认 list 为 null，以便在 $pg > 1 时返回空列表
$response = ['list' => null];

if ($pg === '1' && !empty($t)) {
    $category_list_as_videos = [];
    $filePath = WJ_DIR . '/' . $t . '.txt'; // 指定要处理的文件路径

    // 检查文件是否存在且可读
    if (file_exists($filePath) && is_readable($filePath)) {
        $file_basename = basename($filePath, '.txt');
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $has_genre_marker = false;

        foreach ($lines as $line) {
            $trimmed_line = trim($line);
            if (strpos($trimmed_line, ',#genre#') !== false) {
                $has_genre_marker = true;
                $subcategory_name = trim(explode(',', $trimmed_line, 2)[0]);
                $category_list_as_videos[] = [
                    'vod_name' => $subcategory_name,
    //                'vod_pic' => "https://www.252035.xyz/imgs?t=". mt_rand(),
                    'vod_remarks' => $file_basename, // 将源文件名作为备注
                    // vod_id 格式: '文件名|分类名,#genre'
                    'vod_id' => base64_encode($file_basename . '|' . $subcategory_name . ',#genre#')
                ];
            }
        }

        // 如果文件内无#genre#标记，则创建“默认”分类
        if (!$has_genre_marker && !empty($lines)) {
            $category_list_as_videos[] = [
                'vod_name' => '默认',
   //             'vod_pic' =>"https://www.252035.xyz/imgs?t=". mt_rand(),
                'vod_remarks' => $file_basename,
                // vod_id 格式: '文件名|文件名,#genre' (文件名既是文件标识也是分类标识)
                'vod_id' => base64_encode($file_basename . '|' . $file_basename . ',#genre#')
            ];
        }
        // 如果 $pg 是 '1'，则将处理结果赋值给 list
        $response['list'] = $category_list_as_videos;
    } else {
        // 文件不存在或不可读时的处理，例如记录日志或返回错误信息
        // error_log("File not found or not readable: " . $filePath);
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}


// 4. 默认首页 (根据文件名生成主分类) - 已优化
elseif ($filter === 'true') 
//else
{
    $categories = [];
    $category_list_as_videos = [];
    if (is_dir(WJ_DIR)) {
        $files = glob(WJ_DIR . '/*.txt');
        foreach ($files as $file) {
            $file_basename = basename($file, '.txt');
            $categories[] = ['type_id' => $file_basename, 'type_name' => $file_basename];
        }
    }

    $response = [
        'class' => $categories,
        'filters' => (object)[]
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>