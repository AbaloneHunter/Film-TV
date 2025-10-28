<?php
/**
 * TVBox PHP 爬虫脚本
 * 支持JSON/TXT/M3U/DB文件格式
 * 完整加载模式，带热门推荐功能
 */
ini_set('memory_limit', '-1');
// 获取请求参数
$ac = $_GET['ac'] ?? 'detail';
$t = $_GET['t'] ?? '';
$pg = $_GET['pg'] ?? '1';
$ids = $_GET['ids'] ?? '';
$wd = $_GET['wd'] ?? '';
$flag = $_GET['flag'] ?? '';
$id = $_GET['id'] ?? '';

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 性能优化 - 增加超时时间
@set_time_limit(30);

// 根据不同 action 返回数据
switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            echo json_encode(getDetail($ids), JSON_UNESCAPED_UNICODE);
        } elseif (!empty($t)) {
            echo json_encode(getCategory($t, $pg), JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(getHome(), JSON_UNESCAPED_UNICODE);
        }
        break;
    
    case 'search':
        echo json_encode(search($wd, $pg), JSON_UNESCAPED_UNICODE);
        break;
        
    case 'play':
        echo json_encode(getPlay($flag, $id), JSON_UNESCAPED_UNICODE);
        break;
    
    default:
        echo json_encode(['error' => '未知操作: ' . $ac], JSON_UNESCAPED_UNICODE);
}

/**
 * 递归扫描目录 - 支持无限级子文件夹
 */
function scanDirectoryRecursive($dir, $types, $currentDepth = 0, $maxDepth = 20) {
    $files = [];
    
    if (!is_dir($dir) || $currentDepth > $maxDepth) {
        return $files;
    }
    
    $items = @scandir($dir);
    if ($items === false) return $files;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . $item;
        
        if (is_dir($path)) {
            $subFiles = scanDirectoryRecursive($path . '/', $types, $currentDepth + 1, $maxDepth);
            $files = array_merge($files, $subFiles);
        } else {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, $types)) {
                $relativePath = str_replace('/storage/emulated/0/江湖/', '', $path);
                
                $files[] = [
                    'type' => $extension,
                    'path' => $path,
                    'name' => $item,
                    'filename' => pathinfo($item, PATHINFO_FILENAME),
                    'relative_path' => $relativePath,
                    'depth' => $currentDepth
                ];
            }
        }
    }
    
    return $files;
}

/**
 * 获取所有文件列表 - 添加.db支持
 */
function getAllFiles() {
    static $allFiles = null;
    
    if ($allFiles === null) {
        $allFiles = [];
        
        $jsonFiles = scanDirectoryRecursive('/storage/emulated/0/江湖/json/影视/', ['json']);
        $txtFiles = scanDirectoryRecursive('/storage/emulated/0/江湖/wj/', ['txt']);
        $m3uFiles = array_merge(
            scanDirectoryRecursive('/storage/emulated/0/江湖/json/影视/', ['m3u']),
            scanDirectoryRecursive('/storage/emulated/0/江湖/wj/', ['m3u'])
        );
        // 添加.db文件扫描
        $dbFiles = array_merge(
            scanDirectoryRecursive('/storage/emulated/0/江湖/json/影视/', ['db']),
            scanDirectoryRecursive('/storage/emulated/0/江湖/wj/', ['db']),
            scanDirectoryRecursive('/storage/emulated/0/江湖/db/', ['db']) // 新增专门目录
        );
        
        $allFiles = array_merge($jsonFiles, $txtFiles, $m3uFiles, $dbFiles);
        
        usort($allFiles, function($a, $b) {
            return strcmp($a['relative_path'], $b['relative_path']);
        });
    }
    
    return $allFiles;
}

/**
 * 估算文件中的视频数量（快速估算，不实际解析）
 */
function estimateFileVideoCount($file) {
    $path = $file['path'];
    $type = $file['type'];
    
    if (!file_exists($path)) {
        return 0;
    }
    
    $fileSize = filesize($path);
    
    // 根据文件类型和大小快速估算
    switch ($type) {
        case 'json':
            // JSON文件：假设平均每个视频占用1KB
            return $fileSize > 1024 ? intval($fileSize / 1024) : 1;
            
        case 'txt':
            // TXT文件：按行数估算（假设平均每行100字节）
            $lineCount = $fileSize > 100 ? intval($fileSize / 100) : 1;
            return min($lineCount, 10000);
            
        case 'm3u':
            // M3U文件：每2行一个视频
            $lineCount = $fileSize > 200 ? intval($fileSize / 200) : 1;
            return min($lineCount, 5000);
            
        case 'db':
            // DB文件：根据大小估算，假设平均每个视频记录占用500字节
            return $fileSize > 500 ? intval($fileSize / 500) : 1;
            
        default:
            return 0;
    }
}

/**
 * 获取分类列表
 */
function getCategories() {
    static $categories = null;
    
    if ($categories === null) {
        $allFiles = getAllFiles();
        $categories = [];
        
        // 新增热门推荐分类
        $totalFiles = count($allFiles);
        $categories[] = [
            'type_id' => 'hot',
            'type_name' => '🔥热门推荐 (' . $totalFiles . '个文件)',
            'type_file' => 'hot_recommend',
            'source_path' => 'hot',
            'source_type' => 'hot'
        ];
        
        // 文件分类（显示所有文件）
        foreach ($allFiles as $index => $file) {
            $fileType = '';
            $typeIcon = '';
            
            switch ($file['type']) {
                case 'json':
                    $fileType = '[JSON] ';
                    $typeIcon = '📊 ';
                    break;
                case 'txt':
                    $fileType = '[TXT] ';
                    $typeIcon = '📄 ';
                    break;
                case 'm3u':
                    $fileType = '[M3U] ';
                    $typeIcon = '📺 ';
                    break;
                case 'db':
                    $fileType = '[数据库] ';
                    $typeIcon = '🗃️ ';
                    break;
            }
            
            // 显示文件夹路径
            $folderInfo = '';
            if (strpos($file['relative_path'], '/') !== false) {
                $folderPath = dirname($file['relative_path']);
                $folderInfo = ' 📁 ' . $folderPath;
            }
            
            // 估算每个文件的视频数量
            $videoCount = estimateFileVideoCount($file);
            $countDisplay = $videoCount > 0 ? ' (' . number_format($videoCount) . '个视频)' : '';
            
            $categories[] = [
                'type_id' => (string)($index + 1),
                'type_name' => $typeIcon . $fileType . $file['filename'] . $countDisplay . $folderInfo,
                'type_file' => $file['name'],
                'source_path' => $file['path'],
                'source_type' => $file['type'],
                'video_count' => $videoCount
            ];
        }
        
        if (empty($allFiles)) {
            $categories[] = [
                'type_id' => '1',
                'type_name' => '❓ 未找到媒体文件',
                'type_file' => 'empty',
                'source_path' => 'empty',
                'source_type' => 'empty'
            ];
        }
    }
    
    return $categories;
}

/**
 * 解析SQLite数据库文件内容
 */
function parseDbFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => '数据库文件不存在: ' . $filePath];
    }
    
    // 检查SQLite扩展
    if (!extension_loaded('pdo_sqlite')) {
        return ['error' => 'PDO_SQLite扩展不可用，无法读取数据库文件'];
    }
    
    try {
        // 创建数据库连接
        $db = new PDO("sqlite:" . $filePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $videos = [];
        
        // 尝试检测表结构并读取数据
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tables)) {
            return ['error' => '数据库中未找到任何数据表'];
        }
        
        $defaultImages = [
            'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
        ];
        
        foreach ($tables as $table) {
            // 跳过系统表
            if (strpos($table, 'sqlite_') === 0) continue;
            
            // 获取表结构
            $columns = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'name');
            
            // 查找可能的视频字段
            $nameColumn = null;
            $urlColumn = null;
            $picColumn = null;
            $descColumn = null;
            $yearColumn = null;
            $areaColumn = null;
            
            foreach ($columnNames as $col) {
                $lowerCol = strtolower($col);
                if (in_array($lowerCol, ['name', 'title', 'vod_name', 'filename', 'video_name'])) {
                    $nameColumn = $col;
                } elseif (in_array($lowerCol, ['url', 'link', 'vod_url', 'play_url', 'video_url'])) {
                    $urlColumn = $col;
                } elseif (in_array($lowerCol, ['pic', 'image', 'cover', 'vod_pic', 'poster'])) {
                    $picColumn = $col;
                } elseif (in_array($lowerCol, ['desc', 'description', 'content', 'vod_content'])) {
                    $descColumn = $col;
                } elseif (in_array($lowerCol, ['year', 'vod_year'])) {
                    $yearColumn = $col;
                } elseif (in_array($lowerCol, ['area', 'region', 'vod_area'])) {
                    $areaColumn = $col;
                }
            }
            
            // 如果有名称和URL字段，则读取数据
            if ($nameColumn && $urlColumn) {
                $selectColumns = [$nameColumn, $urlColumn];
                if ($picColumn) $selectColumns[] = $picColumn;
                if ($descColumn) $selectColumns[] = $descColumn;
                if ($yearColumn) $selectColumns[] = $yearColumn;
                if ($areaColumn) $selectColumns[] = $areaColumn;
                
                $selectSql = "SELECT " . implode(', ', $selectColumns) . " FROM $table";
                
                $stmt = $db->query($selectSql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $index => $row) {
                    $videoName = $row[$nameColumn] ?? '未知视频';
                    $videoUrl = $row[$urlColumn] ?? '';
                    $videoPic = $row[$picColumn] ?? $defaultImages[$index % count($defaultImages)];
                    $videoDesc = $row[$descColumn] ?? '《' . $videoName . '》的精彩内容';
                    $videoYear = $row[$yearColumn] ?? date('Y');
                    $videoArea = $row[$areaColumn] ?? '中国大陆';
                    
                    // 验证URL有效性
                    $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://'];
                    $hasValidProtocol = false;
                    foreach ($validProtocols as $protocol) {
                        if (stripos($videoUrl, $protocol) === 0) {
                            $hasValidProtocol = true;
                            break;
                        }
                    }
                    
                    if (!$hasValidProtocol) continue;
                    
                    $videos[] = [
                        'vod_id' => 'db_' . md5($filePath) . '_' . $table . '_' . $index,
                        'vod_name' => $videoName,
                        'vod_pic' => $videoPic,
                        'vod_remarks' => '高清',
                        'vod_year' => $videoYear,
                        'vod_area' => $videoArea,
                        'vod_content' => $videoDesc,
                        'vod_play_from' => '数据库源',
                        'vod_play_url' => '正片$' . $videoUrl
                    ];
                    
                    // 内存保护
                    if (count($videos) >= 1000) break 2;
                }
            }
        }
        
        $db = null; // 关闭连接
        
        return $videos;
        
    } catch (PDOException $e) {
        return ['error' => '数据库读取失败: ' . $e->getMessage()];
    }
}

/**
 * 解析JSON文件内容 - 完整加载
 */
function parseJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'JSON文件不存在: ' . $filePath];
    }
    
    $jsonContent = @file_get_contents($filePath);
    if ($jsonContent === false) {
        return ['error' => '无法读取JSON文件: ' . $filePath];
    }
    
    // 处理BOM头
    if (substr($jsonContent, 0, 3) == "\xEF\xBB\xBF") {
        $jsonContent = substr($jsonContent, 3);
    }
    
    $data = json_decode($jsonContent, true);
    if (!$data || !isset($data['list']) || !is_array($data['list'])) {
        return ['error' => 'JSON格式无效或缺少list字段: ' . $filePath];
    }
    
    return $data['list'];
}

/**
 * 解析TXT文件内容 - 流式处理（支持大文件）
 */
function parseTxtFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'TXT文件不存在: ' . $filePath];
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['error' => '无法打开TXT文件: ' . $filePath];
    }
    
    $videos = [];
    $videoCount = 0;
    $lineNumber = 0;
    
    $defaultImages = [
        'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
    ];
    
    // 检测BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBom = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBom) {
        fseek($handle, 3);
    }
    
    $memoryLimit = 50 * 1024 * 1024;
    $startMemory = memory_get_usage();
    
    while (($line = fgets($handle)) !== false) {
        $lineNumber++;
        $line = trim($line);
        
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        
        $separators = [',', "\t", '|', '$', '#'];
        $separatorPos = false;
        
        foreach ($separators as $sep) {
            $pos = strpos($line, $sep);
            if ($pos !== false) {
                $separatorPos = $pos;
                break;
            }
        }
        
        if ($separatorPos === false) continue;
        
        $name = trim(substr($line, 0, $separatorPos));
        $url = trim(substr($line, $separatorPos + 1));
        
        if (empty($name) || empty($url)) continue;
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://'];
        $hasValidProtocol = false;
        foreach ($validProtocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                $hasValidProtocol = true;
                break;
            }
        }
        
        if (!$hasValidProtocol) continue;
        
        $imageIndex = $videoCount % count($defaultImages);
        
        $videos[] = [
            'vod_id' => 'txt_' . md5($filePath) . '_' . $lineNumber,
            'vod_name' => $name,
            'vod_pic' => $defaultImages[$imageIndex],
            'vod_remarks' => '高清',
            'vod_year' => date('Y'),
            'vod_area' => '中国大陆',
            'vod_content' => '《' . $name . '》的精彩内容',
            'vod_play_from' => '在线播放',
            'vod_play_url' => '正片$' . $url
        ];
        
        $videoCount++;
        
        // 内存保护
        if ($videoCount % 100 === 0) {
            $currentMemory = memory_get_usage() - $startMemory;
            if ($currentMemory > $memoryLimit) break;
            gc_collect_cycles();
        }
        
        if ($videoCount >= 10000) break;
    }
    
    fclose($handle);
    return $videos;
}

/**
 * 解析M3U文件内容 - 流式处理（支持大文件）
 */
function parseM3uFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'M3U文件不存在: ' . $filePath];
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['error' => '无法打开M3U文件: ' . $filePath];
    }
    
    $videos = [];
    $videoCount = 0;
    $currentName = '';
    $currentLogo = '';
    $currentGroup = '';
    
    $defaultImages = [
        'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
    ];
    
    // 检测BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBom = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBom) {
        fseek($handle, 3);
    }
    
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        
        if (strpos($line, '#EXTM3U') === 0) continue;
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentName = '';
            $currentLogo = '';
            $currentGroup = '';
            
            $parts = explode(',', $line, 2);
            if (count($parts) > 1) {
                $currentName = trim($parts[1]);
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $logoMatches)) {
                $currentLogo = trim($logoMatches[1]);
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {
                $currentGroup = trim($groupMatches[1]);
            }
            
            continue;
        }
        
        if ((strpos($line, 'http') === 0 || strpos($line, 'rtmp') === 0 || 
             strpos($line, 'rtsp') === 0 || strpos($line, 'udp') === 0) && 
            !empty($currentName)) {
            
            $imageIndex = $videoCount % count($defaultImages);
            
            $vodPic = $currentLogo;
            if (empty($vodPic) || !filter_var($vodPic, FILTER_VALIDATE_URL)) {
                $vodPic = $defaultImages[$imageIndex];
            }
            
            $playFrom = '直播源';
            if (!empty($currentGroup)) {
                $playFrom = $currentGroup;
            }
            
            $videos[] = [
                'vod_id' => 'm3u_' . md5($filePath) . '_' . $videoCount,
                'vod_name' => $currentName,
                'vod_pic' => $vodPic,
                'vod_remarks' => '直播',
                'vod_year' => date('Y'),
                'vod_area' => '中国大陆',
                'vod_content' => $currentName . '直播频道',
                'vod_play_from' => $playFrom,
                'vod_play_url' => '直播$' . $line
            ];
            
            $videoCount++;
            $currentName = '';
            $currentLogo = '';
            $currentGroup = '';
            
            if ($videoCount >= 5000) break;
        }
    }
    
    fclose($handle);
    return $videos;
}

/**
 * 获取热门推荐视频 - 从所有分类中随机获取
 */
function getHotVideos($page, $pageSize = 10) {
    static $allHotVideos = null;
    static $usedVideoIds = [];
    
    // 如果是第一页，重新生成随机视频
    if ($page == 1) {
        $usedVideoIds = [];
    }
    
    // 收集所有文件的视频
    if ($allHotVideos === null) {
        $allHotVideos = [];
        $allFiles = getAllFiles();
        
        foreach ($allFiles as $file) {
            if (!file_exists($file['path'])) continue;
            
            $videos = [];
            switch ($file['type']) {
                case 'json':
                    $videos = parseJsonFile($file['path']);
                    break;
                case 'txt':
                    $videos = parseTxtFile($file['path']);
                    break;
                case 'm3u':
                    $videos = parseM3uFile($file['path']);
                    break;
                case 'db':
                    $videos = parseDbFile($file['path']);
                    break;
            }
            
            // 如果是错误信息，跳过
            if (isset($videos['error'])) {
                continue;
            }
            
            // 限制每个文件的视频数量，避免内存问题
            if (count($videos) > 100) {
                $videos = array_slice($videos, 0, 100);
            }
            
            $allHotVideos = array_merge($allHotVideos, $videos);
            
            // 内存保护
            if (count($allHotVideos) > 1000) {
                break;
            }
        }
    }
    
    if (empty($allHotVideos)) {
        return [];
    }
    
    // 过滤掉已经使用过的视频
    $availableVideos = [];
    foreach ($allHotVideos as $video) {
        $videoId = $video['vod_id'] ?? '';
        if (!in_array($videoId, $usedVideoIds)) {
            $availableVideos[] = $video;
        }
    }
    
    // 如果可用视频不足，重新开始（实现无限翻页）
    if (empty($availableVideos)) {
        $usedVideoIds = [];
        $availableVideos = $allHotVideos;
    }
    
    // 随机选择视频
    $selectedVideos = [];
    $neededCount = min($pageSize, count($availableVideos));
    
    if ($neededCount > 0) {
        $randomKeys = array_rand($availableVideos, $neededCount);
        if (!is_array($randomKeys)) {
            $randomKeys = [$randomKeys];
        }
        
        foreach ($randomKeys as $key) {
            $selectedVideo = $availableVideos[$key];
            $selectedVideos[] = $selectedVideo;
            $usedVideoIds[] = $selectedVideo['vod_id'] ?? '';
        }
    }
    
    return $selectedVideos;
}

/**
 * 首页数据
 */
function getHome() {
    $categories = getCategories();
    
    if (empty($categories)) {
        return ['error' => '未找到任何文件'];
    }
    
    return [
        'class' => $categories
    ];
}

/**
 * 分类列表
 */
function getCategory($tid, $pg) {
    $categories = getCategories();
    
    if (empty($categories)) {
        return ['error' => '未找到任何分类'];
    }
    
    // 热门推荐分类处理
    if ($tid === 'hot') {
        $currentPage = intval($pg);
        if ($currentPage < 1) $currentPage = 1;
        
        $hotVideos = getHotVideos($currentPage, 10);
        
        if (empty($hotVideos)) {
            return [
                'page' => $currentPage,
                'pagecount' => 9999, // 支持无限翻页
                'limit' => 10,
                'total' => 0,
                'list' => []
            ];
        }
        
        $formattedVideos = [];
        foreach ($hotVideos as $video) {
            $formattedVideos[] = formatVideoItem($video);
        }
        
        return [
            'page' => $currentPage,
            'pagecount' => 9999, // 支持无限翻页
            'limit' => 10,
            'total' => 999999, // 大数字表示无限内容
            'list' => $formattedVideos
        ];
    }
    
    // 找到对应的文件分类
    $targetCategory = null;
    foreach ($categories as $category) {
        if ($category['type_id'] === $tid) {
            $targetCategory = $category;
            break;
        }
    }
    
    if (!$targetCategory) {
        return ['error' => '分类未找到: ' . $tid];
    }
    
    if ($targetCategory['source_type'] === 'empty') {
        return [
            'page' => 1,
            'pagecount' => 1,
            'limit' => 10,
            'total' => 0,
            'list' => []
        ];
    }
    
    // 文件分类：一次性加载完整内容
    $categoryVideos = [];
    
    if (file_exists($targetCategory['source_path'])) {
        switch ($targetCategory['source_type']) {
            case 'json':
                $categoryVideos = parseJsonFile($targetCategory['source_path']);
                break;
            case 'txt':
                $categoryVideos = parseTxtFile($targetCategory['source_path']);
                break;
            case 'm3u':
                $categoryVideos = parseM3uFile($targetCategory['source_path']);
                break;
            case 'db':
                $categoryVideos = parseDbFile($targetCategory['source_path']);
                break;
        }
    }
    
    // 检查是否是错误信息
    if (isset($categoryVideos['error'])) {
        return ['error' => $categoryVideos['error']];
    }
    
    if (empty($categoryVideos)) {
        return ['error' => '在文件中未找到视频: ' . $targetCategory['type_name']];
    }
    
    // 分页处理
    $pageSize = 10;
    $total = count($categoryVideos);
    $pageCount = ceil($total / $pageSize);
    $currentPage = intval($pg);
    
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
 * 格式化视频项
 */
function formatVideoItem($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? '未知视频',
        'vod_pic' => $video['vod_pic'] ?? 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg',
        'vod_remarks' => $video['vod_remarks'] ?? '高清',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? '中国大陆'
    ];
}

/**
 * 视频详情
 */
function getDetail($ids) {
    $idArray = explode(',', $ids);
    $result = [];
    
    foreach ($idArray as $id) {
        $video = findVideoById($id);
        if ($video) {
            $result[] = formatVideoDetail($video);
        } else {
            $result[] = [
                'vod_id' => $id,
                'vod_name' => '视频 ' . $id,
                'vod_pic' => 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg',
                'vod_remarks' => '高清',
                'vod_content' => '视频详情内容',
                'vod_play_from' => '在线播放',
                'vod_play_url' => '正片$https://example.com/video'
            ];
        }
    }
    
    return ['list' => $result];
}

/**
 * 按ID查找视频
 */
function findVideoById($id) {
    $allFiles = getAllFiles();
    
    if (strpos($id, 'txt_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 3) {
            $fileHash = $parts[1];
            $lineNumber = $parts[2];
            
            foreach ($allFiles as $file) {
                if ($file['type'] === 'txt' && md5($file['path']) === $fileHash) {
                    return findTxtVideoByLine($file['path'], $lineNumber);
                }
            }
        }
    } elseif (strpos($id, 'm3u_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 3) {
            $fileHash = $parts[1];
            $lineNumber = $parts[2];
            
            foreach ($allFiles as $file) {
                if ($file['type'] === 'm3u' && md5($file['path']) === $fileHash) {
                    return findM3uVideoByLine($file['path'], $lineNumber);
                }
            }
        }
    } elseif (strpos($id, 'db_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[1];
            $tableName = $parts[2];
            $videoIndex = $parts[3];
            
            foreach ($allFiles as $file) {
                if ($file['type'] === 'db' && md5($file['path']) === $fileHash) {
                    return findDbVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } else {
        foreach ($allFiles as $file) {
            if ($file['type'] === 'json') {
                $videos = parseJsonFile($file['path']);
                foreach ($videos as $video) {
                    if (isset($video['vod_id']) && $video['vod_id'] == $id) {
                        return $video;
                    }
                }
            }
        }
    }
    
    return null;
}

/**
 * 在TXT文件中按行号查找视频
 */
function findTxtVideoByLine($filePath, $targetLineNumber) {
    if (!file_exists($filePath)) {
        return null;
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    
    $currentLine = 0;
    $video = null;
    
    $defaultImages = [
        'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
    ];
    
    // 检测BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBom = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBom) {
        fseek($handle, 3);
    }
    
    while (($line = fgets($handle)) !== false) {
        $currentLine++;
        $line = trim($line);
        
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        
        if ($currentLine == $targetLineNumber) {
            $separators = [',', "\t", '|', '$', '#'];
            $separatorPos = false;
            
            foreach ($separators as $sep) {
                $pos = strpos($line, $sep);
                if ($pos !== false) {
                    $separatorPos = $pos;
                    break;
                }
            }
            
            if ($separatorPos !== false) {
                $name = trim(substr($line, 0, $separatorPos));
                $url = trim(substr($line, $separatorPos + 1));
                
                if (!empty($name) && !empty($url)) {
                    $imageIndex = $currentLine % count($defaultImages);
                    
                    $video = [
                        'vod_id' => 'txt_' . md5($filePath) . '_' . $currentLine,
                        'vod_name' => $name,
                        'vod_pic' => $defaultImages[$imageIndex],
                        'vod_remarks' => '高清',
                        'vod_year' => date('Y'),
                        'vod_area' => '中国大陆',
                        'vod_content' => '《' . $name . '》的精彩内容',
                        'vod_play_from' => '在线播放',
                        'vod_play_url' => '正片$' . $url
                    ];
                }
            }
            break;
        }
    }
    
    fclose($handle);
    return $video;
}

/**
 * 在M3U文件中按行号查找视频
 */
function findM3uVideoByLine($filePath, $targetLineNumber) {
    if (!file_exists($filePath)) {
        return null;
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    
    $currentLine = 0;
    $video = null;
    $currentName = '';
    $currentLogo = '';
    $currentGroup = '';
    
    $defaultImages = [
        'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
    ];
    
    // 检测BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBom = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBom) {
        fseek($handle, 3);
    }
    
    while (($line = fgets($handle)) !== false) {
        $currentLine++;
        $line = trim($line);
        if ($line === '') continue;
        
        if (strpos($line, '#EXTM3U') === 0) {
            continue;
        }
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentName = '';
            $currentLogo = '';
            $currentGroup = '';
            
            $parts = explode(',', $line, 2);
            if (count($parts) > 1) {
                $currentName = trim($parts[1]);
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $logoMatches)) {
                $currentLogo = trim($logoMatches[1]);
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {
                $currentGroup = trim($groupMatches[1]);
            }
            
            continue;
        }
        
        if ((strpos($line, 'http') === 0 || strpos($line, 'rtmp') === 0 || 
             strpos($line, 'rtsp') === 0 || strpos($line, 'udp') === 0) && 
            !empty($currentName)) {
            
            if ($currentLine == $targetLineNumber) {
                $imageIndex = $currentLine % count($defaultImages);
                
                $vodPic = $currentLogo;
                if (empty($vodPic) || !filter_var($vodPic, FILTER_VALIDATE_URL)) {
                    $vodPic = $defaultImages[$imageIndex];
                }
                
                $playFrom = '直播源';
                if (!empty($currentGroup)) {
                    $playFrom = $currentGroup;
                }
                
                $video = [
                    'vod_id' => 'm3u_' . md5($filePath) . '_' . $currentLine,
                    'vod_name' => $currentName,
                    'vod_pic' => $vodPic,
                    'vod_remarks' => '直播',
                    'vod_year' => date('Y'),
                    'vod_area' => '中国大陆',
                    'vod_content' => $currentName . '直播频道',
                    'vod_play_from' => $playFrom,
                    'vod_play_url' => '直播$' . $line
                ];
                break;
            }
            
            $currentName = '';
            $currentLogo = '';
            $currentGroup = '';
        }
    }
    
    fclose($handle);
    return $video;
}

/**
 * 在数据库文件中按索引查找视频
 */
function findDbVideoByIndex($filePath, $tableName, $videoIndex) {
    if (!file_exists($filePath) || !extension_loaded('pdo_sqlite')) {
        return null;
    }
    
    try {
        $db = new PDO("sqlite:" . $filePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 获取表结构
        $columns = $db->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        
        // 查找可能的视频字段
        $nameColumn = null;
        $urlColumn = null;
        $picColumn = null;
        $descColumn = null;
        $yearColumn = null;
        $areaColumn = null;
        
        foreach ($columnNames as $col) {
            $lowerCol = strtolower($col);
            if (in_array($lowerCol, ['name', 'title', 'vod_name', 'filename', 'video_name'])) {
                $nameColumn = $col;
            } elseif (in_array($lowerCol, ['url', 'link', 'vod_url', 'play_url', 'video_url'])) {
                $urlColumn = $col;
            } elseif (in_array($lowerCol, ['pic', 'image', 'cover', 'vod_pic', 'poster'])) {
                $picColumn = $col;
            } elseif (in_array($lowerCol, ['desc', 'description', 'content', 'vod_content'])) {
                $descColumn = $col;
            } elseif (in_array($lowerCol, ['year', 'vod_year'])) {
                $yearColumn = $col;
            } elseif (in_array($lowerCol, ['area', 'region', 'vod_area'])) {
                $areaColumn = $col;
            }
        }
        
        if ($nameColumn && $urlColumn) {
            $selectColumns = [$nameColumn, $urlColumn];
            if ($picColumn) $selectColumns[] = $picColumn;
            if ($descColumn) $selectColumns[] = $descColumn;
            if ($yearColumn) $selectColumns[] = $yearColumn;
            if ($areaColumn) $selectColumns[] = $areaColumn;
            
            $selectSql = "SELECT " . implode(', ', $selectColumns) . " FROM $tableName LIMIT 1 OFFSET " . intval($videoIndex);
            $stmt = $db->query($selectSql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $defaultImages = [
                    'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg'
                ];
                
                $videoName = $row[$nameColumn] ?? '未知视频';
                $videoUrl = $row[$urlColumn] ?? '';
                $videoPic = $row[$picColumn] ?? $defaultImages[intval($videoIndex) % count($defaultImages)];
                $videoDesc = $row[$descColumn] ?? '《' . $videoName . '》的精彩内容';
                $videoYear = $row[$yearColumn] ?? date('Y');
                $videoArea = $row[$areaColumn] ?? '中国大陆';
                
                $video = [
                    'vod_id' => 'db_' . md5($filePath) . '_' . $tableName . '_' . $videoIndex,
                    'vod_name' => $videoName,
                    'vod_pic' => $videoPic,
                    'vod_remarks' => '高清',
                    'vod_year' => $videoYear,
                    'vod_area' => $videoArea,
                    'vod_content' => $videoDesc,
                    'vod_play_from' => '数据库源',
                    'vod_play_url' => '正片$' . $videoUrl
                ];
                
                $db = null;
                return $video;
            }
        }
        
        $db = null;
        return null;
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 搜索
 */
function search($keyword, $page) {
    if (empty($keyword)) {
        return ['error' => '请输入搜索关键词'];
    }
    
    $searchResults = [];
    $allFiles = getAllFiles();
    
    $searchLimit = 3;
    $searchedFiles = 0;
    
    foreach ($allFiles as $file) {
        if ($searchedFiles >= $searchLimit) break;
        
        $videos = [];
        switch ($file['type']) {
            case 'json':
                $videos = parseJsonFile($file['path']);
                break;
            case 'txt':
                $videos = parseTxtFile($file['path']);
                break;
            case 'm3u':
                $videos = parseM3uFile($file['path']);
                break;
            case 'db':
                $videos = parseDbFile($file['path']);
                break;
        }
        
        // 跳过错误结果
        if (isset($videos['error'])) {
            continue;
        }
        
        foreach ($videos as $video) {
            if (stripos($video['vod_name'] ?? '', $keyword) !== false) {
                $searchResults[] = formatVideoItem($video);
                
                if (count($searchResults) >= 30) break 2;
            }
        }
        
        $searchedFiles++;
    }
    
    if (empty($searchResults)) {
        return ['error' => '未找到相关视频内容'];
    }
    
    $pageSize = 10;
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
 * 格式化视频详情
 */
function formatVideoDetail($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? '未知视频',
        'vod_pic' => $video['vod_pic'] ?? 'https://img3.doubanio.com/view/photo/m_ratio_poster/public/p2921303452.jpg',
        'vod_remarks' => $video['vod_remarks'] ?? '高清',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? '中国大陆',
        'vod_director' => $video['vod_director'] ?? '',
        'vod_actor' => $video['vod_actor'] ?? '',
        'vod_content' => $video['vod_content'] ?? '视频详情内容',
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