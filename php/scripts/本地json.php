<?php
/**
 * TVBox PHP 爬虫脚本 - 自动读取所有JSON文件（包含子文件夹）
 * 简单合并所有数据，不按文件夹分组
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
 * 递归获取所有JSON文件（包含子文件夹）
 */
function getAllJsonFiles($dir = null) {
    if ($dir === null) {
        $dir = '/storage/emulated/0/lz/json/';
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
            $subFiles = getAllJsonFiles($path . '/');
            $files = array_merge($files, $subFiles);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'json') {
            $files[] = $path;
        }
    }
    
    return $files;
}

/**
 * 从所有JSON文件中读取数据并合并
 */
function getAllVideoData() {
    static $allData = null;
    
    if ($allData === null) {
        $allData = [];
        $jsonFiles = getAllJsonFiles();
        
        foreach ($jsonFiles as $filePath) {
            if (file_exists($filePath)) {
                $jsonContent = file_get_contents($filePath);
                $data = json_decode($jsonContent, true);
                
                if ($data && isset($data['list']) && is_array($data['list'])) {
                    // 直接合并所有视频数据
                    $allData = array_merge($allData, $data['list']);
                }
            }
        }
    }
    
    return $allData;
}

/**
 * 获取分类列表（按原始JSON文件，不按文件夹）
 */
function getCategories() {
    $jsonFiles = getAllJsonFiles();
    $categories = [];
    $typeId = 1;
    
    foreach ($jsonFiles as $filePath) {
        // 只显示文件名作为分类，不显示文件夹路径
        $filename = basename($filePath);
        $categoryName = pathinfo($filename, PATHINFO_FILENAME);
        
        $categories[] = [
            'type_id' => (string)$typeId,
            'type_name' => $categoryName,
            'type_file' => $filename
        ];
        $typeId++;
    }
    
    return $categories;
}

/**
 * 首页数据
 */
function getHome() {
    $allVideos = getAllVideoData();
    $categories = getCategories();
    
    if (empty($allVideos)) {
        return ['error' => 'No video data found'];
    }
    
    if (empty($categories)) {
        return ['error' => 'No JSON files found'];
    }
    
    // 随机选择一些视频作为推荐
    $recommendCount = min(20, count($allVideos));
    if ($recommendCount > 0) {
        $randomKeys = array_rand($allVideos, $recommendCount);
        
        $recommendList = [];
        if (is_array($randomKeys)) {
            foreach ($randomKeys as $key) {
                $recommendList[] = formatVideoItem($allVideos[$key]);
            }
        } else {
            $recommendList[] = formatVideoItem($allVideos[$randomKeys]);
        }
    } else {
        $recommendList = [];
    }
    
    return [
        'class' => $categories,
        'list' => $recommendList
    ];
}

/**
 * 分类列表
 */
function getCategory($tid, $page) {
    $categories = getCategories();
    $allVideos = getAllVideoData();
    
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
    
    // 读取该分类对应的JSON文件
    $categoryVideos = [];
    $filePath = '/storage/emulated/0/lz/json/' . $targetCategory['type_file'];
    
    // 先尝试直接路径
    if (!file_exists($filePath)) {
        // 如果直接路径不存在，递归查找
        $allFiles = getAllJsonFiles();
        foreach ($allFiles as $file) {
            if (basename($file) === $targetCategory['type_file']) {
                $filePath = $file;
                break;
            }
        }
    }
    
    if (file_exists($filePath)) {
        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);
        
        if ($data && isset($data['list']) && is_array($data['list'])) {
            $categoryVideos = $data['list'];
        }
    }
    
    if (empty($categoryVideos)) {
        return ['error' => 'No videos found in this category'];
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
        $formattedVideos[] = formatVideoItem($video);
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
    $allVideos = getAllVideoData();
    $result = [];
    
    foreach ($idArray as $id) {
        foreach ($allVideos as $video) {
            if (isset($video['vod_id']) && $video['vod_id'] == $id) {
                $result[] = formatVideoDetail($video);
                break;
            }
        }
    }
    
    if (empty($result)) {
        return ['error' => 'Video not found'];
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
    
    $allVideos = getAllVideoData();
    $searchResults = [];
    
    foreach ($allVideos as $video) {
        // 在视频名称、演员、导演、内容中搜索
        $searchFields = [
            $video['vod_name'] ?? '',
            $video['vod_actor'] ?? '',
            $video['vod_director'] ?? '',
            $video['vod_content'] ?? ''
        ];
        
        foreach ($searchFields as $field) {
            if (stripos($field, $keyword) !== false) {
                $searchResults[] = $video;
                break;
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
    
    $formattedResults = [];
    foreach ($pagedResults as $video) {
        $formattedResults[] = formatVideoItem($video);
    }
    
    return [
        'page' => $currentPage,
        'pagecount' => $pageCount,
        'limit' => $pageSize,
        'total' => $total,
        'list' => $formattedResults
    ];
}

/**
 * 格式化视频项（列表用）
 */
function formatVideoItem($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? '',
        'vod_pic' => $video['vod_pic'] ?? '',
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? ''
    ];
}

/**
 * 格式化视频详情
 */
function formatVideoDetail($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? '',
        'vod_pic' => $video['vod_pic'] ?? '',
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? '',
        'vod_director' => $video['vod_director'] ?? '',
        'vod_actor' => $video['vod_actor'] ?? '',
        'vod_content' => $video['vod_content'] ?? '',
        'vod_play_from' => $video['vod_play_from'] ?? 'default',
        'vod_play_url' => $video['vod_play_url'] ?? ''
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