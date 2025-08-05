<?php
setlocale(LC_ALL, 'en_US.utf8');
// --- 配置区 ---
$json_directory = __DIR__ . '/json/';

// --- 响应头 ---
header('Content-Type: application/json; charset=utf-8');

// --- 逻辑区 ---

// 统一获取各类请求参数
$ids = $_GET['ids'] ?? '';           // 获取详情ID
$wd = $_GET['wd'] ?? '';           // 获取详情ID
$t = $_GET['t'] ?? null; // 获取分类ID


if (!empty($wd)) {
    $files = glob($json_directory . '*.json');
    $found_items = []; // 存储所有匹配的项目
    
    // 使用关键词作为搜索模式
    $search_pattern = $wd;
    
    foreach ($files as $file) {
        // 先快速扫描文件内容，如果连关键词都不存在，就没必要解析整个JSON文件
        $content = file_get_contents($file);
        if (strpos($content, $search_pattern) === false) {
            continue; // 关键词不在这个文件里，跳到下一个文件
        }
        
        // 如果找到了关键词，再解码JSON进行详细匹配
        $data = json_decode($content, true);
        if (isset($data['list']) && is_array($data['list'])) {
            foreach ($data['list'] as $item) {
                // 在item的各个字段中搜索关键词
                $item_content = json_encode($item, JSON_UNESCAPED_UNICODE);
                
                // 检查关键词是否存在于item中（不区分大小写）
                if (stripos($item_content, $search_pattern) !== false) {
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
/**
 * 优先处理详情请求 (ac=detail&ids=...)
 */
if (!empty($ids)) {

    $files = glob($json_directory . '*.json');
    $video_found = false;

    foreach ($files as $file) {
        // 直接读取文件内容
        $content = file_get_contents($file);
        
        // 解码JSON。json_decode会自动处理冒号前后的空格
        $data = json_decode($content, true);

        // 检查解码是否成功以及 'list' 键是否存在
        if (json_last_error() === JSON_ERROR_NONE && isset($data['list']) && is_array($data['list'])) {
            foreach ($data['list'] as $item) {
                // 精确匹配 vod_id
                if (isset($item['vod_id']) && $item['vod_id'] == $ids) {
                    
                    // 找到了！按标准格式封装响应
                    $response = ['list' => [$item]];
                    
                    // 输出JSON并终止脚本
                    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $video_found = true;
                    // 使用 break 2; 跳出内外两层循环，提高效率
                    break 2; 
                }
            }
        }
    }

    // 如果遍历完所有文件都没找到
    if (!$video_found) {
        //http_response_code(404);
        echo json_encode(['list' => [], 'msg' => 'Video ID not found.']);
    }

    exit; // 详情请求处理完毕
}


/**
 * 处理分类请求 (t=...)
 */
if (!empty($t)) {
    $file_path = realpath($json_directory . $t);

    if ($file_path && strpos($file_path, realpath($json_directory)) === 0 && file_exists($file_path)) {
        readfile($file_path); // 直接输出分类JSON文件的内容
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Category not found.']);
    }
    exit;
}


/**
 * 首页请求，返回所有分类
 */
$files = glob($json_directory . '*.json');
$categories = [];

foreach ($files as $file) {
    $fileName = basename($file);
    $typeName = pathinfo($fileName, PATHINFO_FILENAME);
    
    $categories[] = [
        'type_id'   => $fileName,
        'type_name' => $typeName
    ];
}

$response = [
    'class'   => $categories,
    'filters' => (object)[]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>