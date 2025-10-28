<?php
/**
 * TVBox PHP 爬虫脚本 - 纯TXT版本
 * 专门处理 /storage/emulated/0/lz/wj/ 下的TXT文件
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
 * 递归获取TXT文件夹下的所有文件
 */
function getAllTxtFiles($dir = null) {
    if ($dir === null) {
        $dir = '/storage/emulated/0/lz/wj/';
    }
    
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . $item;
        
        if (is_dir($path)) {
            // 递归读取子文件夹
            $subFiles = getAllTxtFiles($path . '/');
            $files = array_merge($files, $subFiles);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'txt') {
            $files[] = [
                'path' => $path,
                'name' => $item,
                'filename' => pathinfo($item, PATHINFO_FILENAME)
            ];
        }
    }
    
    return $files;
}

/**
 * 解析TXT文件内容
 */
function parseTxtFile($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $videos = [];
    $videoId = 1;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue; // 跳过空行和注释行
        }
        
        // 解析格式：影片名称, 播放地址
        if (strpos($line, ',') !== false) {
            $parts = explode(',', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $url = trim($parts[1]);
                
                // 跳过分类标题行
                if (strpos($name, '采集电影') !== false || strpos($name, '#genere#') !== false) {
                    continue;
                }
                
                if (!empty($name) && !empty($url) && strpos($url, 'http') === 0) {
                    // 从名称中提取分类信息
                    $category = '电影';
                    $cleanName = $name;
                    
                    $prefixes = [
                        '动作片-' => '动作片',
                        '喜剧片-' => '喜剧片', 
                        '爱情片-' => '爱情片',
                        '恐怖片-' => '恐怖片',
                        '科幻片-' => '科幻片',
                        '悬疑片-' => '悬疑片',
                        '剧情片-' => '剧情片',
                        '战争片-' => '战争片',
                        '动画片-' => '动画片'
                    ];
                    
                    foreach ($prefixes as $prefix => $cat) {
                        if (strpos($name, $prefix) === 0) {
                            $category = $cat;
                            $cleanName = str_replace($prefix, '', $name);
                            break;
                        }
                    }
                    
                    $videos[] = [
                        'vod_id' => 'txt_' . pathinfo($filePath, PATHINFO_FILENAME) . '_' . $videoId,
                        'vod_name' => $cleanName,
                        'vod_pic' => 'https://via.placeholder.com/300x400.png?text=' . urlencode(mb_substr($cleanName, 0, 10)),
                        'vod_remarks' => 'HD',
                        'vod_year' => '2024',
                        'vod_area' => '中国大陆',
                        'vod_director' => '未知',
                        'vod_actor' => '未知',
                        'vod_content' => $cleanName . '的剧情介绍',
                        'vod_play_from' => 'default',
                        'vod_play_url' => '正片$' . $url,
                        'category' => $category
                    ];
                    $videoId++;
                }
            }
        }
    }
    
    return $videos;
}

/**
 * 获取所有分类
 */
function getCategories() {
    $txtFiles = getAllTxtFiles();
    $categories = [];
    $typeId = 1;
    
    foreach ($txtFiles as $file) {
        $categories[] = [
            'type_id' => (string)$typeId,
            'type_name' => $file['filename'],
            'type_file' => $file['name'],
            'source_path' => $file['path']
        ];
        $typeId++;
    }
    
    return $categories;
}

/**
 * 首页数据
 */
function getHome() {
    $categories = getCategories();
    
    if (empty($categories)) {
        return ['error' => 'No TXT files found in wj folder'];
    }
    
    // 显示一些统计信息
    $totalFiles = count($categories);
    
    return [
        'class' => $categories,
        'list' => [
            [
                'vod_id' => 'info_1',
                'vod_name' => 'TXT数据源',
                'vod_pic' => 'https://via.placeholder.com/300x400.png?text=TXT',
                'vod_remarks' => "共{$totalFiles}个文件"
            ]
        ]
    ];
}

/**
 * 分类列表
 */
function getCategory($tid, $page) {
    $categories = getCategories();
    
    if (empty($categories)) {
        return ['error' => 'No categories found'];
    }
    
    // 找到对应的分类
    $targetCategory = null;
    foreach ($categories as $category) {
        if ($category['type_id'] === $tid) {
            $targetCategory = $category;
            break;
        }
    }
    
    if (!$targetCategory) {
        return ['error' => 'Category not found'];
    }
    
    // 读取该TXT文件的内容
    $categoryVideos = parseTxtFile($targetCategory['source_path']);
    
    if (empty($categoryVideos)) {
        return ['error' => 'No videos found in this TXT file'];
    }
    
    // 分页处理
    $pageSize = 20;
    $total = count($categoryVideos);
    $pageCount = ceil($total / $pageSize);
    $currentPage = intval($page);
    
    if ($currentPage < 1) $currentPage = 1;
    if ($currentPage > $pageCount) $currentPage = $pageCount;
    
    $start = ($currentPage - 1) * $pageSize;
    $pagedVideos = array_slice($categoryVideos, $start, $pageSize);
    
    $formattedVideos = [];
    foreach ($pagedVideos as $video) {
        $formattedVideos[] = [
            'vod_id' => $video['vod_id'],
            'vod_name' => $video['vod_name'],
            'vod_pic' => $video['vod_pic'],
            'vod_remarks' => $video['vod_remarks'],
            'vod_year' => $video['vod_year'],
            'vod_area' => $video['vod_area']
        ];
    }
    
    return [
        'page' => $currentPage,
        'pagecount' => $pageCount,
        'limit' => $pageSize,
        'total' => $total,
        'list' => $formattedVideos
    ];
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $idArray = explode(',', $ids);
    $result = [];
    
    foreach ($idArray as $id) {
        // 从ID中提取文件名信息
        if (strpos($id, 'txt_') === 0) {
            $parts = explode('_', $id);
            if (count($parts) >= 3) {
                $filename = $parts[1];
                $videoNumber = $parts[2];
                
                // 找到对应的TXT文件
                $categories = getCategories();
                foreach ($categories as $category) {
                    if (strpos($category['type_file'], $filename) !== false) {
                        $videos = parseTxtFile($category['source_path']);
                        foreach ($videos as $video) {
                            if ($video['vod_id'] === $id) {
                                $result[] = [
                                    'vod_id' => $video['vod_id'],
                                    'vod_name' => $video['vod_name'],
                                    'vod_pic' => $video['vod_pic'],
                                    'vod_remarks' => $video['vod_remarks'],
                                    'vod_year' => $video['vod_year'],
                                    'vod_area' => $video['vod_area'],
                                    'vod_director' => $video['vod_director'],
                                    'vod_actor' => $video['vod_actor'],
                                    'vod_content' => $video['vod_content'],
                                    'vod_play_from' => $video['vod_play_from'],
                                    'vod_play_url' => $video['vod_play_url']
                                ];
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        
        // 如果没找到，返回默认详情
        if (count($result) < count($idArray)) {
            $result[] = [
                'vod_id' => $id,
                'vod_name' => '视频 ' . $id,
                'vod_pic' => 'https://via.placeholder.com/300x400.png?text=Detail',
                'vod_remarks' => 'HD',
                'vod_year' => '2024',
                'vod_area' => '中国大陆',
                'vod_director' => '未知',
                'vod_actor' => '未知',
                'vod_content' => '视频详情内容',
                'vod_play_from' => 'default',
                'vod_play_url' => '正片$https://example.com/video.m3u8'
            ];
        }
    }
    
    return ['list' => $result];
}

/**
 * 搜索
 */
function search($keyword, $page) {
    if (empty($keyword)) {
        return ['error' => 'Keyword is required'];
    }
    
    $categories = getCategories();
    $searchResults = [];
    
    // 在所有TXT文件中搜索
    foreach ($categories as $category) {
        $videos = parseTxtFile($category['source_path']);
        foreach ($videos as $video) {
            if (stripos($video['vod_name'], $keyword) !== false) {
                $searchResults[] = [
                    'vod_id' => $video['vod_id'],
                    'vod_name' => $video['vod_name'],
                    'vod_pic' => $video['vod_pic'],
                    'vod_remarks' => $video['vod_remarks'],
                    'vod_year' => $video['vod_year'],
                    'vod_area' => $video['vod_area']
                ];
            }
            
            // 限制搜索结果数量
            if (count($searchResults) >= 100) {
                break 2;
            }
        }
    }
    
    if (empty($searchResults)) {
        return ['error' => 'No search results'];
    }
    
    // 分页处理
    $pageSize = 20;
    $total = count($searchResults);
    $pageCount = ceil($total / $pageSize);
    $currentPage = intval($page);
    
    if ($currentPage < 1) $currentPage = 1;
    if ($currentPage > $pageCount) $currentPage = $pageCount;
    
    $start = ($currentPage - 1) * $pageSize;
    $pagedResults = array_slice($searchResults, $start, $pageSize);
    
    return [
        'page' => $currentPage,
        'pagecount' => $pageCount,
        'limit' => $pageSize,
        'total' => $total,
        'list' => $pagedResults
    ];
}

/**
 * 获取播放地址
 */
function getPlay($flag, $id) {
    return [
        'parse' => 0,
        'playUrl' => '',
        'url' => $id
    ];
}
?>