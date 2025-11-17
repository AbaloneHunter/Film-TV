<?php
// ==================== 第一部分：基础配置和常量定义 ====================
ini_set('memory_limit', '-1');
@set_time_limit(300);
// 基础扫描路径 - 媒体文件和数据库文件的根目录
define('BASE_SCAN_PATH', '/storage/emulated/0/lg/autoloader/php');
// 最大扫描深度 - 文件夹递归扫描的最大层级，防止无限递归
define('MAX_SCAN_DEPTH', 50);
// 数据库兼容模式 - 是否启用数据库兼容性处理
define('DB_COMPAT_MODE', true);
// 最大数据库结果数 - 从单个数据库表中读取的最大记录数量
define('MAX_DB_RESULTS', 5000);
// 数据库扫描深度 - 数据库文件扫描的深度限制
define('DB_SCAN_DEPTH', 10000);
// 显示模式配置
define('DISPLAY_MODE', 'both'); // 'aggregated': 聚合模式, 'single': 单资源模式, 'both': 两种都显示

$SUPPORTED_DB_TABLES = [
    'video' => '/^(videos?|film|movie|tv|series|影视|视频)/i',
    'category' => '/^(categor(y|ies)|type|分类|类型)/i',
    'magnet' => '/^(magnet|bt|torrent|种子|磁力)/i',
    'channel' => '/^(channel|tv_channel|live|频道|直播)/i',
    'uploader' => '/^(uploader|user|上传者)/i'
];

$DB_FIELD_MAPPING = [
    'id' => ['id', 'vid', 'video_id', 'film_id'],
    'name' => ['name', 'title', 'video_name', 'film_name', 'vod_name'],
    'url' => ['url', 'link', 'play_url', 'video_url', 'vod_url'],
    'magnet' => ['magnet', 'magnet_url', 'magnet_link', 'bt_url'],
    'image' => ['image', 'pic', 'cover', 'poster', 'vod_pic'],
    'category' => ['category', 'type', 'class', 'vod_type'],
    'year' => ['year', 'vod_year'],
    'area' => ['area', 'region', 'vod_area'],
    'actor' => ['actor', 'star', 'vod_actor'],
    'director' => ['director', 'vod_director'],
    'content' => ['content', 'desc', 'description', 'vod_content'],
    'data' => ['data', 'json_data', 'info']
];

// 支持的媒体文件扩展名
$MEDIA_EXTENSIONS = [
    'video' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'mpeg', 'mpg'],
    'audio' => ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma']
];

header('Content-Type: application/json; charset=utf-8');
// ==================== 第二部分：请求参数处理和路由分发 ====================
$ac = $_GET['ac'] ?? 'detail';
$t = $_GET['t'] ?? '';
$pg = $_GET['pg'] ?? '1';
$ids = $_GET['ids'] ?? '';
$wd = $_GET['wd'] ?? '';
$flag = $_GET['flag'] ?? '';
$id = $_GET['id'] ?? '';
$play = $_GET['play'] ?? '';

switch ($ac) {
    case 'detail':
        if (!empty($ids)) {
            $result = getDetail($ids);
        } elseif (!empty($t)) {
            $result = getCategory($t, $pg);
        } else {
            $result = getHome();
        }
        break;
    
    case 'search':
        $result = search($wd, $pg);
        break;
        
    case 'play':
        $result = getPlay($flag, $id);
        break;
    
    default:
        $result = ['error' => 'Unknown action: ' . $ac];
}

if (!empty($play)) {
    $result = directPlayUrl($play);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
// ==================== 第三部分：链接处理和验证函数 ====================

// 修复：增强磁力链接识别
function isMagnetLink($link) {
    if (empty($link)) return false;
    
    // 更全面的磁力链接识别
    $magnetPatterns = [
        '/^magnet:\?xt=urn:btih:([a-zA-Z0-9]{32,40})/i',
        '/^magnet:\?xt=urn:btih:([a-zA-Z0-9]{32})/i',
        '/^magnet:\?xt=urn:sha1:([a-zA-Z0-9]{40})/i',
        '/^magnet:\?dn=.*&xt=urn:btih:/i'
    ];
    
    foreach ($magnetPatterns as $pattern) {
        if (preg_match($pattern, $link)) {
            return true;
        }
    }
    
    // 基础检查
    if (strpos($link, 'magnet:?xt=urn:btih:') === 0) {
        return true;
    }
    if (strpos($link, 'magnet:?xt=urn:sha1:') === 0) {
        return true;
    }
    
    return false;
}

// 修复：增强电驴链接识别
function isEd2kLink($link) {
    if (empty($link)) return false;
    
    $ed2kPatterns = [
        '/^ed2k:\/\/\|file\|[^\|]+\|\d+\|([a-fA-F0-9]{32})\|/',
        '/^ed2k:\/\/\|file\|[^\|]+\|\d+\|\//'
    ];
    
    foreach ($ed2kPatterns as $pattern) {
        if (preg_match($pattern, $link)) {
            return true;
        }
    }
    
    if (strpos($link, 'ed2k://|file|') === 0) {
        return true;
    }
    
    return false;
}

// 修复：新增链接验证函数
function isValidLink($link) {
    if (empty($link)) return false;
    
    // 支持的协议列表（大幅扩展）
    $validProtocols = [
        'http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 
        'magnet:', 'ed2k://', 'ftp://', 'ftps://', 'sftp://',
        'thunder://', 'flashget://', 'qqdl://'
    ];
    
    foreach ($validProtocols as $protocol) {
        if (stripos($link, $protocol) === 0) {
            return true;
        }
    }
    
    // 允许没有协议但包含常见域名的链接
    $commonDomains = ['.com', '.org', '.net', '.tv', '.cc', '.me', '.io'];
    foreach ($commonDomains as $domain) {
        if (stripos($link, $domain) !== false) {
            return true;
        }
    }
    
    return false;
}

// 修复：增强磁力链接文件名提取
function getFileNameFromMagnet($magnetLink) {
    // 尝试从dn参数提取文件名
    if (preg_match('/&dn=([^&]+)/i', $magnetLink, $matches)) {
        $filename = urldecode($matches[1]);
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);
        return $filename ?: 'Magnet Resource';
    }
    
    // 尝试其他常见参数
    if (preg_match('/&tr=[^&]*&dn=([^&]+)/i', $magnetLink, $matches)) {
        $filename = urldecode($matches[1]);
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);
        return $filename ?: 'Magnet Resource';
    }
    
    // 从xt参数提取哈希值作为备用名称
    if (preg_match('/xt=urn:btih:([a-zA-Z0-9]{32,40})/i', $magnetLink, $matches)) {
        $hash = strtoupper($matches[1]);
        return 'Magnet_' . substr($hash, 0, 8);
    }
    
    return 'Magnet Resource';
}

// 修复：增强电驴链接文件名提取
function getFileNameFromEd2k($ed2kLink) {
    if (preg_match('/\|file\|([^\|]+)\|/i', $ed2kLink, $matches)) {
        $filename = urldecode($matches[1]);
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);
        return $filename ?: 'Ed2k Resource';
    }
    
    return 'Ed2k Resource';
}
// ==================== 第四部分：TXT文件解析功能 ====================

// 修复：增强TXT文件解析主函数
function parseTxtFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'TXT file not exist: ' . basename($filePath)];
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['error' => 'Cannot open TXT file: ' . basename($filePath)];
    }
    
    $videoList = [];
    $lineNumber = 0;
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    // 处理BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
        fseek($handle, 3);
    }
    
    // 读取所有行并解析
    $allLines = [];
    while (($line = fgets($handle)) !== false) {
        $lineNumber++;
        $line = trim($line);
        
        // 跳过空行和注释行
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || $line[0] === '//') {
            continue;
        }
        
        $link = '';
        $name = '';
        $isMagnet = false;
        $isEd2k = false;
        
        // 首先检查是否是纯磁力链接或电驴链接
        if (isMagnetLink($line)) {
            $link = $line;
            $name = getFileNameFromMagnet($line);
            $isMagnet = true;
        }
        elseif (isEd2kLink($line)) {
            $link = $line;
            $name = getFileNameFromEd2k($line);
            $isEd2k = true;
        }
        else {
            // 尝试使用分隔符分割名称和链接
            $separators = [',', "\t", '|', '$', '#', ';', '：', ' '];
            $separatorPos = false;
            $usedSeparator = '';
            
            foreach ($separators as $sep) {
                $pos = strpos($line, $sep);
                if ($pos !== false) {
                    $separatorPos = $pos;
                    $usedSeparator = $sep;
                    break;
                }
            }
            
            if ($separatorPos !== false) {
                $namePart = trim(substr($line, 0, $separatorPos));
                $linkPart = trim(substr($line, $separatorPos + strlen($usedSeparator)));
                
                // 验证链接部分
                if (isMagnetLink($linkPart)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);
                    $isMagnet = true;
                } elseif (isEd2kLink($linkPart)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);
                    $isEd2k = true;
                } elseif (isValidLink($linkPart)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : 'Online Video';
                } else {
                    // 如果链接部分无效，尝试整行作为链接
                    if (isValidLink($line)) {
                        $link = $line;
                        $name = 'Online Video';
                    }
                }
            } else {
                // 如果没有分隔符，整行作为链接
                if (isMagnetLink($line)) {
                    $link = $line;
                    $name = getFileNameFromMagnet($line);
                    $isMagnet = true;
                } elseif (isEd2kLink($line)) {
                    $link = $line;
                    $name = getFileNameFromEd2k($line);
                    $isEd2k = true;
                } elseif (isValidLink($line)) {
                    $link = $line;
                    $name = 'Online Video';
                }
            }
        }
        
        // 最终验证链接和名称
        if (!empty($link) && !empty($name) && isValidLink($link)) {
            $allLines[] = [
                'name' => $name,
                'link' => $link,
                'is_magnet' => $isMagnet,
                'is_ed2k' => $isEd2k,
                'line_number' => $lineNumber
            ];
        }
    }
    
    fclose($handle);
    
    if (empty($allLines)) {
        return [];
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    
    // 根据显示模式决定返回内容
    switch (DISPLAY_MODE) {
        case 'aggregated':
            // 聚合模式：只返回一个聚合项目
            return getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages);
            
        case 'single':
            // 单资源模式：返回所有单独的资源
            return getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages);
            
        case 'both':
        default:
            // 两种模式都显示：先显示聚合项目，再显示单个资源
            $aggregated = getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages);
            $single = getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages);
            return array_merge($aggregated, $single);
    }
}

// 获取聚合模式的TXT视频
function getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages) {
    $videoList = [];
    
    $imgIndex = 0 % count($defaultImages);
    
    // 构建播放列表 - 使用实际名字作为线路名称
    $playUrls = [];
    foreach ($allLines as $index => $lineData) {
        $playSource = $lineData['name'];
        // 如果是磁力链接或电驴链接，在名称后添加类型标识
        if ($lineData['is_magnet']) {
            $playSource = $lineData['name'] . ' [磁力]';
        } elseif ($lineData['is_ed2k']) {
            $playSource = $lineData['name'] . ' [电驴]';
        }
        
        $playUrls[] = $playSource . '$' . $lineData['link'];
    }
    
    $playUrlStr = implode('#', $playUrls);
    
    // 创建单个视频项目，包含所有线路
    $videoList[] = [
        'vod_id' => 'txt_aggregated_' . md5($filePath),
        'vod_name' => '[聚合] ' . $fileName,
        'vod_pic' => $defaultImages[$imgIndex],
        'vod_remarks' => count($allLines) . '个资源',
        'vod_year' => date('Y'),
        'vod_area' => 'China',
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allLines) . " 个资源\n文件路径: " . $filePath . "\n【聚合模式】所有资源合并到一个项目中",
        'vod_play_from' => '资源列表',
        'vod_play_url' => $playUrlStr,
        'is_aggregated' => true
    ];
    
    return $videoList;
}

// 获取单资源模式的TXT视频
function getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages) {
    $videoList = [];
    
    // 单资源模式：每个资源都显示为单独的视频
    foreach ($allLines as $index => $lineData) {
        $imgIndex = $index % count($defaultImages);
        
        $playSource = 'Online';
        if ($lineData['is_magnet']) {
            $playSource = 'Magnet';
        } elseif ($lineData['is_ed2k']) {
            $playSource = 'Ed2k';
        }
        
        $videoList[] = [
            'vod_id' => 'txt_single_' . md5($filePath) . '_' . $lineData['line_number'],
            'vod_name' => $lineData['name'],
            'vod_pic' => $defaultImages[$imgIndex],
            'vod_remarks' => $lineData['is_magnet'] ? 'Magnet' : ($lineData['is_ed2k'] ? 'Ed2k' : 'HD'),
            'vod_year' => date('Y'),
            'vod_area' => 'China',
            'vod_content' => $lineData['name'] . ' - 来自TXT文件的资源',
            'vod_play_from' => $playSource,
            'vod_play_url' => 'Play$' . $lineData['link'],
            'is_single' => true
        ];
    }
    
    return $videoList;
}
// ==================== 第五部分：文件扫描和基础功能 ====================

function directPlayUrl($playUrl) {
    $playUrl = urldecode($playUrl);
    
    // 如果是本地文件路径，转换为file://协议
    if (file_exists($playUrl) && strpos($playUrl, '://') === false) {
        $playUrl = 'file://' . $playUrl;
    }
    
    $playType = 'video';
    $needParse = 0;
    
    if (strpos($playUrl, 'magnet:') === 0) {
        $playType = 'magnet';
    } elseif (strpos($playUrl, 'ed2k://') === 0) {
        $playType = 'ed2k';
    } elseif (strpos($playUrl, '.m3u8') !== false) {
        $playType = 'hls';
    } elseif (strpos($playUrl, 'file://') === 0) {
        $playType = 'local';
    }
    
    return [
        'parse' => $needParse,
        'playUrl' => '',
        'url' => $playUrl,
        'header' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Referer' => parse_url($playUrl, PHP_URL_SCHEME) . '://' . parse_url($playUrl, PHP_URL_HOST)
        ],
        'type' => $playType
    ];
}

function scanDirectoryRecursive($directory, $fileTypes, $currentDepth = 1, $maxDepth = 50) {
    $fileList = [];
    
    if (!is_dir($directory) || $currentDepth > $maxDepth) {
        return $fileList;
    }
    
    try {
        $items = @scandir($directory);
        if ($items === false) {
            return $fileList;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = rtrim($directory, '/') . '/' . $item;
            
            if (is_dir($fullPath)) {
                $subFiles = scanDirectoryRecursive($fullPath . '/', $fileTypes, $currentDepth + 1, $maxDepth);
                $fileList = array_merge($fileList, $subFiles);
            } else {
                $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                if (in_array($extension, $fileTypes)) {
                    $relativePath = str_replace(BASE_SCAN_PATH, '', $fullPath);
                    
                    $fileList[] = [
                        'type' => $extension,
                        'path' => $fullPath,
                        'name' => $item,
                        'filename' => pathinfo($item, PATHINFO_FILENAME),
                        'relative_path' => $relativePath,
                        'depth' => $currentDepth
                    ];
                }
            }
        }
    } catch (Exception $e) {
        return $fileList;
    }
    
    return $fileList;
}

function getAllFiles() {
    static $allFiles = null;
    
    if ($allFiles === null) {
        $allFiles = [];
        
        if (!is_dir(BASE_SCAN_PATH)) {
            return $allFiles;
        }
        
        // 扫描所有支持的文件类型，包括.magnets
        $allFiles = scanDirectoryRecursive(BASE_SCAN_PATH, [
            'json', 'txt', 'magnets', 'm3u', 'm3u8', 'db', 'sqlite', 'sqlite3', 'db3'
        ]);
        
        usort($allFiles, function($a, $b) {
            return strcmp($a['relative_path'], $b['relative_path']);
        });
    }
    
    return $allFiles;
}

function detectFileType($filePath) {
    if (!file_exists($filePath)) {
        return 'unknown';
    }
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // .magnets 后缀直接识别为磁力文件
    if ($extension === 'magnets') {
        return 'magnet_txt';
    }
    
    return $extension;
}

// 新增：格式化文件大小
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// 新增：获取媒体文件缩略图
function getMediaThumbnail($file, $mediaType) {
    $defaultImages = [
        'video' => 'https://www.252035.xyz/imgs?t=1335527662',
        'audio' => 'https://www.252035.xyz/imgs?t=1335527662'
    ];
    
    return $defaultImages[$mediaType] ?? 'https://www.252035.xyz/imgs?t=1335527662';
}

// 新增：获取媒体文件（视频和音频）
function getMediaFiles() {
    static $mediaFiles = null;
    
    if ($mediaFiles === null) {
        global $MEDIA_EXTENSIONS;
        $mediaFiles = [
            'video' => [],
            'audio' => [],
            'aggregated' => [] // 新增聚合项目
        ];
        
        if (!is_dir(BASE_SCAN_PATH)) {
            return $mediaFiles;
        }
        
        // 扫描视频文件
        $videoFiles = scanDirectoryRecursive(BASE_SCAN_PATH, $MEDIA_EXTENSIONS['video']);
        foreach ($videoFiles as $file) {
            $mediaFiles['video'][] = $file;
        }
        
        // 扫描音频文件
        $audioFiles = scanDirectoryRecursive(BASE_SCAN_PATH, $MEDIA_EXTENSIONS['audio']);
        foreach ($audioFiles as $file) {
            $mediaFiles['audio'][] = $file;
        }
        
        // 创建聚合项目
        if (!empty($videoFiles) || !empty($audioFiles)) {
            $mediaFiles['aggregated'] = createMediaAggregatedProjects($videoFiles, $audioFiles);
        }
        
        // 按文件名排序
        usort($mediaFiles['video'], function($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });
        
        usort($mediaFiles['audio'], function($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });
    }
    
    return $mediaFiles;
}
// ==================== 第六部分：JSON和M3U文件解析 ====================

function parseJsonFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'JSON file not exist: ' . basename($filePath)];
    }
    
    $jsonContent = @file_get_contents($filePath);
    if ($jsonContent === false) {
        return ['error' => 'Cannot read JSON file: ' . basename($filePath)];
    }
    
    if (substr($jsonContent, 0, 3) == "\xEF\xBB\xBF") {
        $jsonContent = substr($jsonContent, 3);
    }
    
    $data = json_decode($jsonContent, true);
    if (!$data) {
        return ['error' => 'Invalid JSON format: ' . basename($filePath)];
    }
    
    if (!isset($data['list']) || !is_array($data['list'])) {
        return ['error' => 'Invalid JSON format or missing list field: ' . basename($filePath)];
    }
    
    return $data['list'];
}

function parseM3uFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'M3U file not exist: ' . basename($filePath)];
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return ['error' => 'Cannot open M3U file: ' . basename($filePath)];
    }
    
    $videoList = [];
    $lineNumber = 0;
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
        fseek($handle, 3);
    }
    
    // 读取所有频道
    $allChannels = [];
    $currentName = '';
    $currentIcon = '';
    $currentGroup = '';
    
    while (($line = fgets($handle)) !== false) {
        $lineNumber++;
        $line = trim($line);
        if ($line === '') continue;
        
        if (strpos($line, '#EXTM3U') === 0) {
            continue;
        }
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentName = '';
            $currentIcon = '';
            $currentGroup = '';
            
            $parts = explode(',', $line, 2);
            if (count($parts) > 1) {
                $currentName = trim($parts[1]);
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {
                $currentIcon = trim($iconMatches[1]);
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {
                $currentGroup = trim($groupMatches[1]);
            }
            continue;
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];
        $hasValidProtocol = false;
        foreach ($validProtocols as $protocol) {
            if (stripos($line, $protocol) === 0) {
                $hasValidProtocol = true;
                break;
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {
            $allChannels[] = [
                'name' => $currentName,
                'url' => $line,
                'icon' => $currentIcon,
                'group' => $currentGroup,
                'line_number' => $lineNumber
            ];
            
            $currentName = '';
            $currentIcon = '';
            $currentGroup = '';
        }
    }
    
    fclose($handle);
    
    if (empty($allChannels)) {
        return [];
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    
    // 根据显示模式决定返回内容
    switch (DISPLAY_MODE) {
        case 'aggregated':
            // 聚合模式：只返回一个聚合项目
            return getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages);
            
        case 'single':
            // 单资源模式：返回所有单独的频道
            return getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages);
            
        case 'both':
        default:
            // 两种模式都显示：先显示聚合项目，再显示单个频道
            $aggregated = getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages);
            $single = getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages);
            return array_merge($aggregated, $single);
    }
}

// 获取聚合模式的M3U视频
function getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages) {
    $videoList = [];
    
    $imgIndex = 0 % count($defaultImages);
    
    // 构建播放列表 - 使用频道名称作为线路名称
    $playUrls = [];
    foreach ($allChannels as $index => $channelData) {
        $playSource = $channelData['name'];
        // 如果有分组信息，添加到名称中
        if (!empty($channelData['group'])) {
            $playSource = $channelData['name'] . ' [' . $channelData['group'] . ']';
        }
        
        $playUrls[] = $playSource . '$' . $channelData['url'];
    }
    
    $playUrlStr = implode('#', $playUrls);
    
    // 创建单个视频项目，包含所有频道
    $videoList[] = [
        'vod_id' => 'm3u_aggregated_' . md5($filePath),
        'vod_name' => '[聚合] ' . $fileName,
        'vod_pic' => $defaultImages[$imgIndex],
        'vod_remarks' => count($allChannels) . '个频道',
        'vod_year' => date('Y'),
        'vod_area' => 'China',
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allChannels) . " 个电视频道\n文件路径: " . $filePath . "\n【聚合模式】所有频道合并到一个项目中",
        'vod_play_from' => '频道列表',
        'vod_play_url' => $playUrlStr,
        'is_aggregated' => true
    ];
    
    return $videoList;
}

// 获取单资源模式的M3U视频
function getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages) {
    $videoList = [];
    
    // 单资源模式：每个频道都显示为单独的视频
    foreach ($allChannels as $index => $channelData) {
        $imgIndex = $index % count($defaultImages);
        
        $videoCover = $channelData['icon'];
        if (empty($videoCover) || !filter_var($videoCover, FILTER_VALIDATE_URL)) {
            $videoCover = $defaultImages[$imgIndex];
        }
        
        $playSource = '直播';
        if (!empty($channelData['group'])) {
            $playSource = $channelData['group'];
        }
        
        if (strpos($channelData['url'], 'magnet:') === 0) {
            $playSource = '磁力';
        } elseif (strpos($channelData['url'], 'ed2k://') === 0) {
            $playSource = '电驴';
        }
        
        $videoList[] = [
            'vod_id' => 'm3u_single_' . md5($filePath) . '_' . $channelData['line_number'],
            'vod_name' => $channelData['name'],
            'vod_pic' => $videoCover,
            'vod_remarks' => '直播',
            'vod_year' => date('Y'),
            'vod_area' => '中国大陆',
            'vod_content' => $channelData['name'] . ' 直播频道',
            'vod_play_from' => $playSource,
            'vod_play_url' => 'Play$' . $channelData['url'],
            'is_single' => true
        ];
    }
    
    return $videoList;
}
// ==================== 第七部分：数据库解析功能 ====================

function parseDatabaseFile($filePath) {
    global $SUPPORTED_DB_TABLES, $DB_FIELD_MAPPING;
    
    if (!file_exists($filePath)) {
        return ['error' => 'Database file not exist: ' . basename($filePath)];
    }
    
    if (!extension_loaded('pdo_sqlite')) {
        return ['error' => 'PDO_SQLite extension not available'];
    }
    
    try {
        $db = new PDO("sqlite:" . $filePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tables)) {
            return ['error' => 'No tables found in database'];
        }
        
        $dbType = identifyDatabaseType($tables, $db);
        
        switch ($dbType) {
            case 'video_category':
                return parseVideoCategoryDatabase($db, $filePath);
            case 'magnet_database':
                return parseMagnetDatabase($db, $filePath);
            case 'live_channel':
                return parseLiveChannelDatabase($db, $filePath);
            case 'universal_video':
                return parseUniversalVideoDatabase($db, $filePath);
            case 'json_data_database':
                return parseJsonDataDatabase($db, $filePath);
            default:
                return parseAutoDetectDatabase($db, $filePath, $tables);
        }
        
    } catch (PDOException $e) {
        return ['error' => 'Database read failed: ' . $e->getMessage()];
    }
}

function identifyDatabaseType($tables, $db) {
    global $SUPPORTED_DB_TABLES;
    
    // 检查是否有包含JSON数据的表
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        // 检查表结构是否有data字段
        try {
            $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $fieldNames = array_column($fieldInfo, 'name');
            
            if (in_array('data', $fieldNames) || in_array('json_data', $fieldNames)) {
                // 检查数据内容是否包含JSON格式的磁力链接
                $sampleData = $db->query("SELECT data FROM $table LIMIT 1")->fetch(PDO::FETCH_COLUMN);
                if ($sampleData && strpos($sampleData, '"magnet":') !== false) {
                    return 'json_data_database';
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    if (in_array('videos', $tables) && in_array('categories', $tables)) {
        return 'video_category';
    }
    
    foreach ($tables as $table) {
        if (preg_match($SUPPORTED_DB_TABLES['magnet'], $table)) {
            return 'magnet_database';
        }
    }
    
    foreach ($tables as $table) {
        if (preg_match($SUPPORTED_DB_TABLES['channel'], $table)) {
            return 'live_channel';
        }
    }
    
    foreach ($tables as $table) {
        if (preg_match($SUPPORTED_DB_TABLES['video'], $table)) {
            return 'universal_video';
        }
    }
    
    foreach ($tables as $table) {
        if (preg_match($SUPPORTED_DB_TABLES['uploader'], $table)) {
            return 'json_data_database';
        }
    }
    
    return 'auto_detect';
}

// 新增：解析JSON数据数据库（专门处理包含磁力链接的数据库）
function parseJsonDataDatabase($db, $filePath) {
    $videoList = [];
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $jsonField = null;
        foreach ($fieldNames as $field) {
            $lowerField = strtolower($field);
            if (in_array($lowerField, ['data', 'json_data', 'info', 'content', 'json'])) {
                $jsonField = $field;
                break;
            }
        }
        
        if (!$jsonField) {
            continue;
        }
        
        // 获取所有包含JSON数据的记录
        try {
            $results = $db->query("SELECT $jsonField FROM $table LIMIT " . MAX_DB_RESULTS)->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($results as $index => $jsonData) {
                if (empty($jsonData)) continue;
                
                $videoData = json_decode($jsonData, true);
                if (!$videoData || !is_array($videoData)) continue;
                
                // 从JSON数据中提取视频信息
                $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';
                $videoLink = '';
                $playSource = 'Database';
                
                // 优先使用磁力链接
                if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {
                    $videoLink = $videoData['magnet'];
                    $playSource = 'Magnet';
                } 
                // 其次使用torrent链接
                elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {
                    $videoLink = $videoData['torrent'];
                    $playSource = 'Torrent';
                }
                // 最后使用普通链接
                elseif (isset($videoData['link']) && !empty($videoData['link'])) {
                    $videoLink = $videoData['link'];
                    if (strpos($videoLink, 'magnet:') === 0) {
                        $playSource = 'Magnet';
                    } elseif (strpos($videoLink, 'ed2k://') === 0) {
                        $playSource = 'Ed2k';
                    }
                }
                
                if (empty($videoLink)) {
                    continue;
                }
                
                // 提取其他信息
                $videoCover = $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[$index % count($defaultImages)];
                $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content';
                $videoYear = $videoData['year'] ?? '';
                $videoArea = $videoData['area'] ?? $videoData['region'] ?? 'International';
                $videoSize = $videoData['size'] ?? '';
                $uploader = $videoData['uploader'] ?? '';
                
                // 构建内容描述
                $content = $videoDesc;
                if (!empty($uploader)) {
                    $content .= "\n上传者: " . $uploader;
                }
                if (!empty($videoSize)) {
                    $content .= "\n大小: " . $videoSize;
                }
                if (isset($videoData['imdb']) && !empty($videoData['imdb'])) {
                    $content .= "\nIMDb: " . $videoData['imdb'];
                }
                
                // 修复：正确传递磁力链接给播放器
                $videoList[] = [
                    'vod_id' => 'json_db_' . md5($filePath) . '_' . $table . '_' . $index,
                    'vod_name' => $videoName,
                    'vod_pic' => $videoCover,
                    'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',
                    'vod_year' => $videoYear,
                    'vod_area' => $videoArea,
                    'vod_content' => $content,
                    'vod_play_from' => $playSource,
                    'vod_play_url' => 'Play$' . $videoLink
                ];
                
                if (count($videoList) >= MAX_DB_RESULTS) {
                    break 2;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    $db = null;
    return $videoList;
}
// ==================== 第八部分：数据库解析续和分类管理 ====================

function parseMagnetDatabase($db, $filePath) {
    $videoList = [];
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        // 检查是否有data字段（包含JSON数据）
        if (in_array('data', $fieldNames)) {
            $results = $db->query("SELECT data FROM $table LIMIT " . MAX_DB_RESULTS)->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($results as $index => $jsonData) {
                if (empty($jsonData)) continue;
                
                $videoData = json_decode($jsonData, true);
                if ($videoData && is_array($videoData)) {
                    $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';
                    $videoLink = '';
                    $playSource = 'Database';
                    
                    if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {
                        $videoLink = $videoData['magnet'];
                        $playSource = 'Magnet';
                    } elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {
                        $videoLink = $videoData['torrent'];
                        $playSource = 'Torrent';
                    }
                    
                    if (empty($videoLink)) continue;
                    
                    // 提取其他信息
                    $videoCover = $videoData['image'] ?? $defaultImages[$index % count($defaultImages)];
                    $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoName . ' content';
                    $videoYear = $videoData['year'] ?? date('Y');
                    $videoArea = $videoData['area'] ?? 'International';
                    $videoSize = $videoData['size'] ?? '';
                    $uploader = $videoData['uploader'] ?? '';
                    
                    // 构建内容描述
                    $content = $videoDesc;
                    if (!empty($uploader)) {
                        $content .= "\n上传者: " . $uploader;
                    }
                    if (!empty($videoSize)) {
                        $content .= "\n大小: " . $videoSize;
                    }
                    
                    // 修复：正确传递磁力链接给播放器
                    $videoList[] = [
                        'vod_id' => 'db_' . md5($filePath) . '_' . $table . '_' . $index,
                        'vod_name' => $videoName,
                        'vod_pic' => $videoCover,
                        'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',
                        'vod_year' => $videoYear,
                        'vod_area' => $videoArea,
                        'vod_content' => $content,
                        'vod_play_from' => $playSource,
                        'vod_play_url' => 'Play$' . $videoLink
                    ];
                    
                    if (count($videoList) >= MAX_DB_RESULTS) break 2;
                }
            }
        }
    }
    
    $db = null;
    return $videoList;
}

function parseVideoCategoryDatabase($db, $filePath) {
    $videoList = [];
    
    $categoryMap = [];
    $categories = $db->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categories as $cat) {
        $categoryMap[$cat['id']] = $cat['name'];
    }
    
    $videos = $db->query("SELECT id, category_id, name, image, actor, director, remarks, pubdate, area, year, content, play_url FROM videos LIMIT " . MAX_DB_RESULTS)->fetchAll(PDO::FETCH_ASSOC);
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    foreach ($videos as $index => $video) {
        $videoName = $video['name'] ?? 'Unknown Video';
        $playUrl = $video['play_url'] ?? '';
        
        if (empty($playUrl)) continue;
        
        $playSource = 'Video';
        if (strpos($playUrl, 'magnet:') === 0) {
            $playSource = 'Magnet';
        } elseif (strpos($playUrl, 'ed2k://') === 0) {
            $playSource = 'Ed2k';
        }
        
        $categoryName = $categoryMap[$video['category_id']] ?? 'Unknown Category';
        
        $videoList[] = [
            'vod_id' => 'video_' . $video['id'],
            'vod_name' => $videoName,
            'vod_pic' => $video['image'] ?? $defaultImages[$index % count($defaultImages)],
            'vod_remarks' => $video['remarks'] ?? 'HD',
            'vod_year' => $video['year'] ?? '',
            'vod_area' => $video['area'] ?? 'China',
            'vod_actor' => $video['actor'] ?? '',
            'vod_director' => $video['director'] ?? '',
            'vod_content' => $video['content'] ?? $videoName . ' content',
            'vod_play_from' => $playSource . ' · ' . $categoryName,
            'vod_play_url' => 'Play$' . $playUrl
        ];
        
        if (count($videoList) >= MAX_DB_RESULTS) break;
    }
    
    return $videoList;
}

function parseUniversalVideoDatabase($db, $filePath) {
    global $DB_FIELD_MAPPING;
    
    $videoList = [];
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $mappedFields = [];
        foreach ($DB_FIELD_MAPPING as $stdField => $possibleFields) {
            foreach ($possibleFields as $candidate) {
                if (in_array($candidate, $fieldNames)) {
                    $mappedFields[$stdField] = $candidate;
                    break;
                }
            }
        }
        
        if (empty($mappedFields['name']) || (empty($mappedFields['url']) && empty($mappedFields['magnet']))) {
            continue;
        }
        
        $selectFields = array_values($mappedFields);
        $querySQL = "SELECT " . implode(', ', $selectFields) . " FROM $table LIMIT " . MAX_DB_RESULTS;
        
        try {
            $stmt = $db->query($querySQL);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $index => $row) {
                $videoName = $row[$mappedFields['name']] ?? 'Unknown Video';
                $videoLink = '';
                $playSource = 'Database';
                
                if (!empty($mappedFields['magnet']) && !empty($row[$mappedFields['magnet']])) {
                    $videoLink = $row[$mappedFields['magnet']];
                    $playSource = 'Magnet';
                } elseif (!empty($mappedFields['url']) && !empty($row[$mappedFields['url']])) {
                    $videoLink = $row[$mappedFields['url']];
                    if (strpos($videoLink, 'magnet:') === 0) {
                        $playSource = 'Magnet';
                    } elseif (strpos($videoLink, 'ed2k://') === 0) {
                        $playSource = 'Ed2k';
                    } elseif (strpos($videoLink, 'http') === 0) {
                        $playSource = 'Online';
                    }
                }
                
                if (empty($videoLink)) {
                    continue;
                }
                
                $videoCover = '';
                if (!empty($mappedFields['image']) && !empty($row[$mappedFields['image']])) {
                    $videoCover = $row[$mappedFields['image']];
                } else {
                    $videoCover = $defaultImages[$index % count($defaultImages)];
                }
                
                $videoInfo = [
                    'vod_id' => 'db_' . md5($filePath) . '_' . $table . '_' . $index,
                    'vod_name' => $videoName,
                    'vod_pic' => $videoCover,
                    'vod_remarks' => 'HD',
                    'vod_year' => !empty($mappedFields['year']) ? ($row[$mappedFields['year']] ?? date('Y')) : date('Y'),
                    'vod_area' => !empty($mappedFields['area']) ? ($row[$mappedFields['area']] ?? 'China') : 'China',
                    'vod_content' => !empty($mappedFields['content']) ? ($row[$mappedFields['content']] ?? $videoName . ' content') : $videoName . ' content',
                    'vod_play_from' => $playSource,
                    'vod_play_url' => 'Play$' . $videoLink
                ];
                
                if (!empty($mappedFields['actor']) && !empty($row[$mappedFields['actor']])) {
                    $videoInfo['vod_actor'] = $row[$mappedFields['actor']];
                }
                
                if (!empty($mappedFields['director']) && !empty($row[$mappedFields['director']])) {
                    $videoInfo['vod_director'] = $row[$mappedFields['director']];
                }
                
                $videoList[] = $videoInfo;
                
                if (count($videoList) >= MAX_DB_RESULTS) {
                    break 2;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    $db = null;
    return $videoList;
}

function parseLiveChannelDatabase($db, $filePath, $resourceName = 'Live Source') {
    $videoList = [];
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $nameField = null;
        $urlField = null;
        $groupField = null;
        $iconField = null;
        
        foreach ($fieldNames as $field) {
            $lowerField = strtolower($field);
            if (in_array($lowerField, ['name', 'title', 'channel_name', 'channel_title'])) {
                $nameField = $field;
            } elseif (in_array($lowerField, ['url', 'link', 'channel_url', 'play_url'])) {
                $urlField = $field;
            } elseif (in_array($lowerField, ['group', 'category', 'type'])) {
                $groupField = $field;
            } elseif (in_array($lowerField, ['logo', 'icon', 'image'])) {
                $iconField = $field;
            }
        }
        
        if (!$nameField || !$urlField) {
            continue;
        }
        
        $selectFields = [$nameField, $urlField];
        if ($groupField) $selectFields[] = $groupField;
        if ($iconField) $selectFields[] = $iconField;
        
        $querySQL = "SELECT " . implode(', ', $selectFields) . " FROM $table LIMIT 1000";
        
        try {
            $stmt = $db->query($querySQL);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $index => $row) {
                $channelName = $row[$nameField] ?? 'Unknown Channel';
                $channelUrl = $row[$urlField] ?? '';
                $channelGroup = $groupField ? ($row[$groupField] ?? 'Live Channel') : 'Live Channel';
                $channelIcon = $iconField ? ($row[$iconField] ?? '') : '';
                
                if (empty($channelUrl)) {
                    continue;
                }
                
                $videoCover = $channelIcon ?: $defaultImages[$index % count($defaultImages)];
                
                $videoList[] = [
                    'vod_id' => 'live_' . md5($filePath) . '_' . $table . '_' . $index,
                    'vod_name' => $channelName,
                    'vod_pic' => $videoCover,
                    'vod_remarks' => 'Live',
                    'vod_year' => date('Y'),
                    'vod_area' => 'China',
                    'vod_content' => $channelName . ' live channel',
                    'vod_play_from' => $resourceName,
                    'vod_play_url' => $resourceName . '$' . $channelUrl
                ];
                
                if (count($videoList) >= 1000) {
                    break 2;
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    $db = null;
    return $videoList;
}

function parseAutoDetectDatabase($db, $filePath, $tables) {
    $videoList = [];
    
    foreach ($tables as $table) {
        if (strpos($table, 'sqlite_') === 0) continue;
        
        $videoList = array_merge($videoList, tryParseGenericTable($db, $filePath, $table));
        $videoList = array_merge($videoList, tryParseJsonTable($db, $filePath, $table));
        
        if (count($videoList) >= MAX_DB_RESULTS) {
            break;
        }
    }
    
    $db = null;
    return $videoList;
}

function tryParseGenericTable($db, $filePath, $table) {
    $videoList = [];
    
    try {
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $possibleNameFields = [];
        $possibleUrlFields = [];
        
        foreach ($fieldNames as $field) {
            $lowerField = strtolower($field);
            if (strpos($lowerField, 'name') !== false || strpos($lowerField, 'title') !== false) {
                $possibleNameFields[] = $field;
            }
            if (strpos($lowerField, 'url') !== false || strpos($lowerField, 'link') !== false || 
                strpos($lowerField, 'magnet') !== false) {
                $possibleUrlFields[] = $field;
            }
        }
        
        if (empty($possibleNameFields) || empty($possibleUrlFields)) {
            return $videoList;
        }
        
        $nameField = $possibleNameFields[0];
        $urlField = $possibleUrlFields[0];
        
        $querySQL = "SELECT $nameField, $urlField FROM $table LIMIT 500";
        $stmt = $db->query($querySQL);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
        
        foreach ($results as $index => $row) {
            $videoName = $row[$nameField] ?? 'Unknown Video';
            $videoUrl = $row[$urlField] ?? '';
            
            if (empty($videoUrl)) {
                continue;
            }
            
            $playSource = 'Database';
            if (strpos($videoUrl, 'magnet:') === 0) {
                $playSource = 'Magnet';
            } elseif (strpos($videoUrl, 'ed2k://') === 0) {
                $playSource = 'Ed2k';
            }
            
            $videoList[] = [
                'vod_id' => 'auto_' . md5($filePath) . '_' . $table . '_' . $index,
                'vod_name' => $videoName,
                'vod_pic' => $defaultImages[$index % count($defaultImages)],
                'vod_remarks' => 'HD',
                'vod_year' => date('Y'),
                'vod_area' => 'China',
                'vod_content' => $videoName . ' content',
                'vod_play_from' => $playSource,
                'vod_play_url' => 'Play$' . $videoUrl
            ];
        }
    } catch (Exception $e) {
    }
    
    return $videoList;
}

function tryParseJsonTable($db, $filePath, $table) {
    $videoList = [];
    
    try {
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $jsonField = null;
        foreach ($fieldNames as $field) {
            $lowerField = strtolower($field);
            if (in_array($lowerField, ['json', 'data', 'info', 'content'])) {
                $jsonField = $field;
                break;
            }
        }
        
        if (!$jsonField) {
            return $videoList;
        }
        
        $querySQL = "SELECT $jsonField FROM $table LIMIT 300";
        $stmt = $db->query($querySQL);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
        
        foreach ($results as $index => $jsonData) {
            if (empty($jsonData)) continue;
            
            $videoData = json_decode($jsonData, true);
            if (!$videoData || !is_array($videoData)) continue;
            
            $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';
            $videoUrl = $videoData['url'] ?? $videoData['magnet'] ?? $videoData['link'] ?? '';
            
            if (empty($videoUrl)) continue;
            
            $playSource = 'Database';
            if (strpos($videoUrl, 'magnet:') === 0) {
                $playSource = 'Magnet';
            } elseif (strpos($videoUrl, 'ed2k://') === 0) {
                $playSource = 'Ed2k';
            }
            
            $videoList[] = [
                'vod_id' => 'json_' . md5($filePath) . '_' . $table . '_' . $index,
                'vod_name' => $videoName,
                'vod_pic' => $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[$index % count($defaultImages)],
                'vod_remarks' => 'HD',
                'vod_year' => $videoData['year'] ?? date('Y'),
                'vod_area' => $videoData['area'] ?? 'China',
                'vod_content' => $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content',
                'vod_play_from' => $playSource,
                'vod_play_url' => 'Play$' . $videoUrl
            ];
            
            if (count($videoList) >= 300) {
                break;
            }
        }
    } catch (Exception $e) {
    }
    
    return $videoList;
}
// ==================== 第九部分：重构分类系统 - 按类型聚合子分类 ====================

// 重构获取分类列表函数 - 按文件类型聚合
function getCategoryList() {
    static $categoryList = null;
    
    if ($categoryList === null) {
        $allFiles = getAllFiles();
        $mediaFiles = getMediaFiles();
        $categoryList = [];
        
        // 计算各类文件数量
        $fileTypeCounts = [
            'json' => 0,
            'txt' => 0, 
            'magnets' => 0,
            'm3u' => 0,
            'm3u8' => 0,
            'db' => 0,
            'sqlite' => 0,
            'sqlite3' => 0,
            'db3' => 0
        ];
        
        foreach ($allFiles as $file) {
            $fileType = $file['type'];
            if (isset($fileTypeCounts[$fileType])) {
                $fileTypeCounts[$fileType]++;
            }
        }
        
        $totalFiles = count($allFiles);
        $totalMedia = count($mediaFiles['video']) + count($mediaFiles['audio']);
        
        // 只保留分类聚合作为唯一分类，包含媒体库
        $totalItems = $totalFiles + ($totalMedia > 0 ? 1 : 0);
        $categoryList[] = [
            'type_id' => 'aggregated',
            'type_name' => '分类聚合 (' . $totalItems . '个项目)',
            'type_file' => 'aggregated',
            'source_path' => 'aggregated',
            'source_type' => 'aggregated',
            'is_aggregated' => true
        ];
        
        if (empty($allFiles) && $totalMedia === 0) {
            $categoryList[] = [
                'type_id' => '1',
                'type_name' => 'No media files found',
                'type_file' => 'empty',
                'source_path' => 'empty',
                'source_type' => 'empty'
            ];
        }
    }
    
    return $categoryList;
}

// 重构获取分类内容函数 - 支持三级分类
function getCategory($categoryId, $page) {
    $categoryList = getCategoryList();
    
    if (empty($categoryList)) {
        return ['error' => 'No categories found'];
    }
    
    // 处理主聚合分类 - 显示类型聚合子分类
    if ($categoryId === 'aggregated') {
        $allFiles = getAllFiles();
        $mediaFiles = getMediaFiles();
        $subCategories = [];
        
        // 先添加媒体库（如果有媒体文件）
        if (!empty($mediaFiles['video']) || !empty($mediaFiles['audio'])) {
            $totalMedia = count($mediaFiles['video']) + count($mediaFiles['audio']);
            $subCategories[] = [
                'vod_id' => 'aggregated_media_library',
                'vod_name' => '媒体库 (' . $totalMedia . '个媒体文件)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '媒体',
                'is_aggregated_sub' => true,
                'is_media_library' => true
            ];
        }
        
        // 按文件类型创建聚合子分类
        $fileTypeCounts = [
            'json' => 0,
            'txt' => 0, 
            'magnets' => 0,
            'm3u' => 0,
            'm3u8' => 0,
            'db' => 0,
            'sqlite' => 0,
            'sqlite3' => 0,
            'db3' => 0
        ];
        
        foreach ($allFiles as $file) {
            $fileType = $file['type'];
            if (isset($fileTypeCounts[$fileType])) {
                $fileTypeCounts[$fileType]++;
            }
        }
        
        // JSON文件分类
        if ($fileTypeCounts['json'] > 0) {
            $subCategories[] = [
                'vod_id' => 'type_json',
                'vod_name' => 'JSON文件 (' . $fileTypeCounts['json'] . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        // TXT文件分类（不包括.magnets）
        if ($fileTypeCounts['txt'] > 0) {
            $subCategories[] = [
                'vod_id' => 'type_txt',
                'vod_name' => 'TXT文件 (' . $fileTypeCounts['txt'] . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        // 磁力文件分类（.magnets）
        if ($fileTypeCounts['magnets'] > 0) {
            $subCategories[] = [
                'vod_id' => 'type_magnets',
                'vod_name' => '磁力文件 (' . $fileTypeCounts['magnets'] . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        // M3U文件分类
        $m3uCount = $fileTypeCounts['m3u'] + $fileTypeCounts['m3u8'];
        if ($m3uCount > 0) {
            $subCategories[] = [
                'vod_id' => 'type_m3u',
                'vod_name' => 'M3U文件 (' . $m3uCount . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        // 数据库文件分类
        $dbCount = $fileTypeCounts['db'] + $fileTypeCounts['sqlite'] + $fileTypeCounts['sqlite3'] + $fileTypeCounts['db3'];
        if ($dbCount > 0) {
            $subCategories[] = [
                'vod_id' => 'type_db',
                'vod_name' => '数据库文件 (' . $dbCount . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        // 磁力数据库文件分类（专门处理包含磁力链接的数据库）
        $magnetDbCount = countMagnetDatabaseFiles($allFiles);
        if ($magnetDbCount > 0) {
            $subCategories[] = [
                'vod_id' => 'type_magnet_db',
                'vod_name' => '磁力数据库 (' . $magnetDbCount . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'is_type_category' => true
            ];
        }
        
        if (empty($subCategories)) {
            return ['error' => 'No items found in aggregated category'];
        }
        
        return [
            'is_sub' => true,
            'list' => $subCategories,
            'page' => 1,
            'pagecount' => 1,
            'limit' => 20,
            'total' => count($subCategories),
            'category_name' => '分类聚合'
        ];
    }
    
    // 处理媒体库子分类
    if ($categoryId === 'aggregated_media_library') {
        $mediaFiles = getMediaFiles();
        $subCategories = [];
        
        // 先添加聚合项目
        if (!empty($mediaFiles['aggregated'])) {
            foreach ($mediaFiles['aggregated'] as $aggregated) {
                $subCategories[] = [
                    'vod_id' => $aggregated['vod_id'],
                    'vod_name' => $aggregated['vod_name'],
                    'vod_pic' => $aggregated['vod_pic'],
                    'vod_remarks' => $aggregated['vod_remarks'],
                    'is_aggregated_sub' => true,
                    'is_media_aggregated' => true
                ];
            }
        }
        
        // 视频子分类
        if (!empty($mediaFiles['video'])) {
            $subCategories[] = [
                'vod_id' => 'media_video',
                'vod_name' => '视频文件 (' . count($mediaFiles['video']) . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '视频',
                'is_media_sub' => true,
                'media_type' => 'video'
            ];
        }
        
        // 音频子分类
        if (!empty($mediaFiles['audio'])) {
            $subCategories[] = [
                'vod_id' => 'media_audio',
                'vod_name' => '音频文件 (' . count($mediaFiles['audio']) . '个)',
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '音频',
                'is_media_sub' => true,
                'media_type' => 'audio'
            ];
        }
        
        if (empty($subCategories)) {
            return ['error' => 'No media files found'];
        }
        
        return [
            'is_sub' => true,
            'list' => $subCategories,
            'page' => 1,
            'pagecount' => 1,
            'limit' => 20,
            'total' => count($subCategories),
            'category_name' => '媒体库'
        ];
    }
    
    // 处理类型聚合子分类 - 显示该类型下的所有文件
    if (strpos($categoryId, 'type_') === 0) {
        $allFiles = getAllFiles();
        $fileType = substr($categoryId, 5); // json, txt, magnets, m3u, db, magnet_db
        
        $subCategories = [];
        
        switch ($fileType) {
            case 'json':
                // JSON文件
                foreach ($allFiles as $index => $file) {
                    if ($file['type'] === 'json') {
                        $subCategories[] = [
                            'vod_id' => 'file_json_' . $index,
                            'vod_name' => 'JSON ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'json'
                        ];
                    }
                }
                break;
                
            case 'txt':
                // TXT文件（不包括.magnets）
                foreach ($allFiles as $index => $file) {
                    if ($file['type'] === 'txt') {
                        $subCategories[] = [
                            'vod_id' => 'file_txt_' . $index,
                            'vod_name' => 'TXT ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'txt'
                        ];
                    }
                }
                break;
                
            case 'magnets':
                // 磁力文件（.magnets）
                foreach ($allFiles as $index => $file) {
                    if ($file['type'] === 'magnets') {
                        $subCategories[] = [
                            'vod_id' => 'file_magnets_' . $index,
                            'vod_name' => 'Magnet ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'magnets'
                        ];
                    }
                }
                break;
                
            case 'm3u':
                // M3U文件
                foreach ($allFiles as $index => $file) {
                    if ($file['type'] === 'm3u' || $file['type'] === 'm3u8') {
                        $subCategories[] = [
                            'vod_id' => 'file_m3u_' . $index,
                            'vod_name' => 'M3U ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'm3u'
                        ];
                    }
                }
                break;
                
            case 'db':
                // 普通数据库文件
                foreach ($allFiles as $index => $file) {
                    if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && !isMagnetDatabaseFile($file['path'])) {
                        $subCategories[] = [
                            'vod_id' => 'file_db_' . $index,
                            'vod_name' => 'Database ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'db'
                        ];
                    }
                }
                break;
                
            case 'magnet_db':
                // 磁力数据库文件
                foreach ($allFiles as $index => $file) {
                    if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($file['path'])) {
                        $subCategories[] = [
                            'vod_id' => 'file_magnet_db_' . $index,
                            'vod_name' => 'Magnet DB ' . $file['filename'],
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                            'vod_remarks' => '文件',
                            'is_file_sub' => true,
                            'file_index' => $index,
                            'file_type' => 'magnet_db'
                        ];
                    }
                }
                break;
        }
        
        if (empty($subCategories)) {
            return ['error' => 'No files found in this category'];
        }
        
        return [
            'is_sub' => true,
            'list' => $subCategories,
            'page' => 1,
            'pagecount' => 1,
            'limit' => 20,
            'total' => count($subCategories),
            'category_name' => getTypeCategoryName($fileType)
        ];
    }
    
    // 处理文件子分类（三级分类） - 显示具体文件内容
    if (strpos($categoryId, 'file_') === 0) {
        $parts = explode('_', $categoryId);
        if (count($parts) >= 3) {
            $fileType = $parts[1]; // json, txt, magnets, m3u, db, magnet_db
            $fileIndex = intval($parts[2]);
            
            $allFiles = getAllFiles();
            
            if (!isset($allFiles[$fileIndex])) {
                return ['error' => 'File not found: ' . $categoryId];
            }
            
            $targetFile = $allFiles[$fileIndex];
            
            // 验证文件类型是否匹配
            $isValidType = false;
            switch ($fileType) {
                case 'json':
                    $isValidType = ($targetFile['type'] === 'json');
                    break;
                case 'txt':
                    $isValidType = ($targetFile['type'] === 'txt');
                    break;
                case 'magnets':
                    $isValidType = ($targetFile['type'] === 'magnets');
                    break;
                case 'm3u':
                    $isValidType = ($targetFile['type'] === 'm3u' || $targetFile['type'] === 'm3u8');
                    break;
                case 'db':
                    $isValidType = in_array($targetFile['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && !isMagnetDatabaseFile($targetFile['path']);
                    break;
                case 'magnet_db':
                    $isValidType = in_array($targetFile['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($targetFile['path']);
                    break;
            }
            
            if (!$isValidType) {
                return ['error' => 'File type mismatch: ' . $categoryId];
            }
            
            $categoryVideos = [];
            
            if (file_exists($targetFile['path'])) {
                switch ($targetFile['type']) {
                    case 'json':
                        $categoryVideos = parseJsonFile($targetFile['path']);
                        break;
                    case 'txt':
                    case 'magnets':
                        $categoryVideos = parseTxtFile($targetFile['path']);
                        break;
                    case 'm3u':
                        $categoryVideos = parseM3uFile($targetFile['path']);
                        break;
                    case 'db':
                    case 'sqlite':
                    case 'sqlite3':
                    case 'db3':
                        $categoryVideos = parseDatabaseFile($targetFile['path']);
                        break;
                }
            }
            
            if (isset($categoryVideos['error'])) {
                return ['error' => $categoryVideos['error']];
            }
            
            if (empty($categoryVideos)) {
                return ['error' => 'No videos found in file: ' . $targetFile['filename']];
            }
            
            $pageSize = 10;
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
                'list' => $formattedVideos,
                'category_name' => $targetFile['filename']
            ];
        }
    }
    
    // 保持原有的媒体文件处理逻辑
    if (strpos($categoryId, 'media_aggregated_') === 0) {
        $mediaFiles = getMediaFiles();
        
        foreach ($mediaFiles['aggregated'] as $aggregated) {
            if ($aggregated['vod_id'] === $categoryId) {
                return [
                    'page' => 1,
                    'pagecount' => 1,
                    'limit' => 1,
                    'total' => 1,
                    'list' => [$aggregated],
                    'category_name' => $aggregated['vod_name']
                ];
            }
        }
        
        return ['error' => 'Aggregated media not found: ' . $categoryId];
    }
    
    if (strpos($categoryId, 'media_') === 0) {
        $mediaType = substr($categoryId, 6);
        $mediaFiles = getMediaFiles();
        
        if (!isset($mediaFiles[$mediaType]) || empty($mediaFiles[$mediaType])) {
            return ['error' => 'No ' . $mediaType . ' files found'];
        }
        
        $mediaList = [];
        foreach ($mediaFiles[$mediaType] as $index => $file) {
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            
            $mediaList[] = [
                'vod_id' => 'media_' . $mediaType . '_' . $index,
                'vod_name' => $file['filename'],
                'vod_pic' => getMediaThumbnail($file, $mediaType),
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,
                'vod_year' => date('Y', filemtime($file['path'])),
                'vod_area' => '本地文件',
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),
                'vod_play_from' => $mediaType === 'video' ? '本地视频' : '本地音频',
                'vod_play_url' => 'Play$' . urlencode($file['path'])
            ];
        }
        
        $pageSize = 10;
        $total = count($mediaList);
        $pageCount = ceil($total / $pageSize);
        $currentPage = intval($page);
        
        if ($currentPage < 1) $currentPage = 1;
        if ($currentPage > $pageCount) $currentPage = $pageCount;
        
        $start = ($currentPage - 1) * $pageSize;
        $pagedMedia = array_slice($mediaList, $start, $pageSize);
        
        return [
            'page' => $currentPage,
            'pagecount' => $pageCount,
            'limit' => $pageSize,
            'total' => $total,
            'list' => $pagedMedia,
            'category_name' => ($mediaType === 'video' ? '视频文件' : '音频文件')
        ];
    }
    
    return ['error' => 'Category not found: ' . $categoryId];
}

// 辅助函数：获取类型分类名称
function getTypeCategoryName($fileType) {
    $names = [
        'json' => 'JSON文件',
        'txt' => 'TXT文件',
        'magnets' => '磁力文件',
        'm3u' => 'M3U文件', 
        'db' => '数据库文件',
        'magnet_db' => '磁力数据库'
    ];
    
    return $names[$fileType] ?? '未知分类';
}

// 辅助函数：检测是否为磁力数据库文件
function isMagnetDatabaseFile($filePath) {
    if (!file_exists($filePath) || !extension_loaded('pdo_sqlite')) {
        return false;
    }
    
    try {
        $db = new PDO("sqlite:" . $filePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            if (strpos($table, 'sqlite_') === 0) continue;
            
            // 检查表结构是否有data字段
            $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $fieldNames = array_column($fieldInfo, 'name');
            
            if (in_array('data', $fieldNames) || in_array('json_data', $fieldNames)) {
                // 检查数据内容是否包含JSON格式的磁力链接
                $sampleData = $db->query("SELECT data FROM $table LIMIT 1")->fetch(PDO::FETCH_COLUMN);
                if ($sampleData && strpos($sampleData, '"magnet":') !== false) {
                    $db = null;
                    return true;
                }
            }
        }
        
        $db = null;
        return false;
        
    } catch (PDOException $e) {
        return false;
    }
}

// 辅助函数：统计磁力数据库文件数量
function countMagnetDatabaseFiles($allFiles) {
    $count = 0;
    foreach ($allFiles as $file) {
        if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($file['path'])) {
            $count++;
        }
    }
    return $count;
}
// ==================== 第十部分：首页和搜索功能 ====================

function getHome() {
    $categoryList = getCategoryList();
    
    if (empty($categoryList)) {
        return ['error' => 'No files found'];
    }
    
    return ['class' => $categoryList];
}

function formatVideoItem($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? 'Unknown Video',
        'vod_pic' => $video['vod_pic'] ?? 'https://www.252035.xyz/imgs?t=1335527662',
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? 'China'
    ];
}

// 增强搜索匹配函数
function searchMatch($text, $keyword) {
    if (empty($text) || empty($keyword)) return false;
    
    $text = strtolower(trim($text));
    $keyword = strtolower(trim($keyword));
    
    // 完全匹配
    if (strpos($text, $keyword) !== false) return true;
    
    // 分词匹配
    $keywords = preg_split('/\s+/', $keyword);
    if (count($keywords) > 1) {
        $matchCount = 0;
        foreach ($keywords as $kw) {
            if (strpos($text, $kw) !== false) {
                $matchCount++;
            }
        }
        // 如果所有关键词都匹配，返回true
        if ($matchCount == count($keywords)) {
            return true;
        }
    }
    
    // 字符包含匹配
    $keywordLength = mb_strlen($keyword, 'UTF-8');
    if ($keywordLength > 1) {
        for ($i = 0; $i < $keywordLength; $i++) {
            $char = mb_substr($keyword, $i, 1, 'UTF-8');
            if (mb_strpos($text, $char, 0, 'UTF-8') === false) return false;
        }
        return true;
    }
    
    return false;
}

// 增强搜索功能，支持新的分类结构
function search($keyword, $page) {
    if (empty($keyword)) {
        return ['error' => 'Please enter search keyword'];
    }
    
    $searchResults = [];
    $allFiles = getAllFiles();
    $mediaFiles = getMediaFiles();
    $categoryList = getCategoryList();
    
    $searchLimit = 20;
    $searchedFiles = 0;
    
    // 搜索数据文件
    foreach ($allFiles as $fileIndex => $file) {
        if ($searchedFiles >= $searchLimit) break;
        
        if (!file_exists($file['path'])) continue;
        
        $fileSize = filesize($file['path']);
        if ($fileSize > 60 * 1024 * 1024) continue;
        
        $videoList = [];
        try {
            switch ($file['type']) {
                case 'json':
                    $videoList = parseJsonFile($file['path']);
                    break;
                case 'txt':
                case 'magnets':
                    $videoList = parseTxtFile($file['path']);
                    break;
                case 'm3u':
                    $videoList = parseM3uFile($file['path']);
                    break;
                case 'db':
                case 'sqlite':
                case 'sqlite3':
                case 'db3':
                    $videoList = parseDatabaseFile($file['path']);
                    break;
                default:
                    continue 2;
            }
        } catch (Exception $e) {
            continue;
        }
        
        if (isset($videoList['error']) || !is_array($videoList) || empty($videoList)) continue;
        
        $fileMatchCount = 0;
        foreach ($videoList as $videoIndex => $video) {
            $videoName = $video['vod_name'] ?? '';
            if (empty($videoName)) continue;
            
            if (searchMatch($videoName, $keyword)) {
                $formattedVideo = formatVideoItem($video);
                if (isset($video['vod_play_from']) && isset($video['vod_play_url'])) {
                    $formattedVideo['vod_play_from'] = $video['vod_play_from'];
                    $formattedVideo['vod_play_url'] = $video['vod_play_url'];
                }
                $searchResults[] = $formattedVideo;
                $fileMatchCount++;
                
                if ($fileMatchCount >= 90) break;
                if (count($searchResults) >= 300) break 2;
            }
        }
        
        $searchedFiles++;
    }
    
    // 搜索媒体文件
    foreach ($mediaFiles['video'] as $index => $file) {
        if (searchMatch($file['filename'], $keyword)) {
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            
            $searchResults[] = [
                'vod_id' => 'media_video_' . $index,
                'vod_name' => $file['filename'],
                'vod_pic' => getMediaThumbnail($file, 'video'),
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,
                'vod_year' => date('Y', filemtime($file['path'])),
                'vod_area' => '本地文件',
                'vod_play_from' => '本地视频',
                'vod_play_url' => 'Play$' . urlencode($file['path'])
            ];
            
            if (count($searchResults) >= 300) break;
        }
    }
    
    foreach ($mediaFiles['audio'] as $index => $file) {
        if (searchMatch($file['filename'], $keyword)) {
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            
            $searchResults[] = [
                'vod_id' => 'media_audio_' . $index,
                'vod_name' => $file['filename'],
                'vod_pic' => getMediaThumbnail($file, 'audio'),
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,
                'vod_year' => date('Y', filemtime($file['path'])),
                'vod_area' => '本地文件',
                'vod_play_from' => '本地音频',
                'vod_play_url' => 'Play$' . urlencode($file['path'])
            ];
            
            if (count($searchResults) >= 300) break;
        }
    }
    
    // 搜索分类名称（支持新的类型分类）
    foreach ($categoryList as $category) {
        if (searchMatch($category['type_name'], $keyword)) {
            $searchResults[] = [
                'vod_id' => $category['type_id'],
                'vod_name' => '[分类] ' . $category['type_name'],
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => '分类',
                'vod_year' => '',
                'vod_area' => '分类',
                'is_category' => true
            ];
        }
    }
    
    if (empty($searchResults)) {
        return ['error' => 'No related videos found'];
    }
    
    // 去重
    $dedupResults = [];
    $existingIds = [];
    foreach ($searchResults as $video) {
        $videoId = $video['vod_id'] ?? $video['vod_name'];
        if (!in_array($videoId, $existingIds)) {
            $dedupResults[] = $video;
            $existingIds[] = $videoId;
        }
    }
    $searchResults = $dedupResults;
    
    // 分页处理
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
// ==================== 第十一部分：播放和详情功能 ====================

function getPlay($flag, $id) {
    $playId = urldecode($id);
    
    // 处理磁力链接
    if (strpos($playId, 'magnet:') === 0) {
        return [
            'parse' => 0,
            'playUrl' => '',
            'url' => $playId,
            'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
            'type' => 'magnet'
        ];
    }
    
    // 处理电驴链接
    if (strpos($playId, 'ed2k://') === 0) {
        return [
            'parse' => 0,
            'playUrl' => '',
            'url' => $playId,
            'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
            'type' => 'ed2k'
        ];
    }
    
    return [
        'parse' => 0,
        'playUrl' => '',
        'url' => $playId,
        'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
        'type' => 'video'
    ];
}

function getDetail($videoId) {
    $idArray = explode(',', $videoId);
    $result = [];
    
    foreach ($idArray as $id) {
        $video = findVideoById($id);
        if ($video) {
            $result[] = formatVideoDetail($video);
        } else {
            $result[] = [
                'vod_id' => $id,
                'vod_name' => 'Video ' . $id,
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                'vod_remarks' => 'HD',
                'vod_content' => 'Video detail content',
                'vod_play_from' => 'Online',
                'vod_play_url' => 'Play$https://example.com/video'
            ];
        }
    }
    
    return ['list' => $result];
}

function findVideoById($id) {
    $allFiles = getAllFiles();
    $mediaFiles = getMediaFiles();
    
    // 处理媒体聚合项目
    if (strpos($id, 'media_aggregated_') === 0) {
        foreach ($mediaFiles['aggregated'] as $aggregated) {
            if ($aggregated['vod_id'] === $id) {
                return $aggregated;
            }
        }
    }
    
    // 处理分类
    if (strpos($id, 'aggregated') === 0) {
        $categoryList = getCategoryList();
        foreach ($categoryList as $category) {
            if ($category['type_id'] === $id) {
                return [
                    'vod_id' => $id,
                    'vod_name' => $category['type_name'],
                    'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',
                    'vod_remarks' => '分类',
                    'vod_year' => '',
                    'vod_area' => '分类',
                    'vod_content' => '分类: ' . $category['type_name'],
                    'vod_play_from' => '分类',
                    'vod_play_url' => ''
                ];
            }
        }
    }
    
    // 处理媒体文件
    if (strpos($id, 'media_video_') === 0) {
        $index = intval(substr($id, 12));
        if (isset($mediaFiles['video'][$index])) {
            $file = $mediaFiles['video'][$index];
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            
            return [
                'vod_id' => 'media_video_' . $index,
                'vod_name' => $file['filename'],
                'vod_pic' => getMediaThumbnail($file, 'video'),
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,
                'vod_year' => date('Y', filemtime($file['path'])),
                'vod_area' => '本地文件',
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),
                'vod_play_from' => '本地视频',
                'vod_play_url' => 'Play$' . urlencode($file['path'])
            ];
        }
    }
    
    if (strpos($id, 'media_audio_') === 0) {
        $index = intval(substr($id, 12));
        if (isset($mediaFiles['audio'][$index])) {
            $file = $mediaFiles['audio'][$index];
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            
            return [
                'vod_id' => 'media_audio_' . $index,
                'vod_name' => $file['filename'],
                'vod_pic' => getMediaThumbnail($file, 'audio'),
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,
                'vod_year' => date('Y', filemtime($file['path'])),
                'vod_area' => '本地文件',
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),
                'vod_play_from' => '本地音频',
                'vod_play_url' => 'Play$' . urlencode($file['path'])
            ];
        }
    }
    
    // 处理聚合TXT文件
    if (strpos($id, 'txt_aggregated_') === 0) {
        $fileHash = substr($id, 15);
        foreach ($allFiles as $file) {
            if (($file['type'] === 'txt' || $file['type'] === 'magnets') && md5($file['path']) === $fileHash) {
                return findTxtVideoByLine($file['path'], 0);
            }
        }
    } 
    // 处理单资源TXT文件
    elseif (strpos($id, 'txt_single_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[2];
            $lineNumber = $parts[3];
            
            foreach ($allFiles as $file) {
                if (($file['type'] === 'txt' || $file['type'] === 'magnets') && md5($file['path']) === $fileHash) {
                    return findSingleTxtVideoByLine($file['path'], $lineNumber);
                }
            }
        }
    } 
    // 处理聚合M3U文件
    elseif (strpos($id, 'm3u_aggregated_') === 0) {
        $fileHash = substr($id, 15);
        foreach ($allFiles as $file) {
            if (in_array($file['type'], ['m3u', 'm3u8']) && md5($file['path']) === $fileHash) {
                return findM3uVideoByLine($file['path'], 0);
            }
        }
    } 
    // 处理单资源M3U文件
    elseif (strpos($id, 'm3u_single_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[2];
            $lineNumber = $parts[3];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['m3u', 'm3u8']) && md5($file['path']) === $fileHash) {
                    return findSingleM3uVideoByLine($file['path'], $lineNumber);
                }
            }
        }
    } 
    // 处理JSON数据库文件
    elseif (strpos($id, 'json_db_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[2];
            $tableName = $parts[3];
            $videoIndex = $parts[4];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } 
    // 处理普通数据库文件
    elseif (strpos($id, 'db_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[1];
            $tableName = $parts[2];
            $videoIndex = $parts[3];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } 
    // 处理视频分类数据库
    elseif (strpos($id, 'video_') === 0) {
        $videoID = substr($id, 6);
        return findCategoryVideoById($videoID);
    } 
    // 处理自动检测数据库
    elseif (strpos($id, 'auto_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[1];
            $tableName = $parts[2];
            $videoIndex = $parts[3];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } 
    // 处理JSON表数据
    elseif (strpos($id, 'json_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[1];
            $tableName = $parts[2];
            $videoIndex = $parts[3];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } 
    // 处理直播频道
    elseif (strpos($id, 'live_') === 0) {
        $parts = explode('_', $id);
        if (count($parts) >= 4) {
            $fileHash = $parts[1];
            $tableName = $parts[2];
            $videoIndex = $parts[3];
            
            foreach ($allFiles as $file) {
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);
                }
            }
        }
    } 
    // 处理JSON文件中的视频
    else {
        foreach ($allFiles as $file) {
            if ($file['type'] === 'json') {
                $videoList = parseJsonFile($file['path']);
                if (is_array($videoList)) {
                    foreach ($videoList as $video) {
                        if (isset($video['vod_id']) && $video['vod_id'] == $id) {
                            return $video;
                        }
                    }
                }
            }
        }
    }
    
    return null;
}
// ==================== 第十二部分：查找辅助函数 ====================

// 查找聚合TXT视频详情
function findTxtVideoByLine($filePath, $targetLine) {
    if (!file_exists($filePath)) {
        return null;
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    
    $currentLine = 0;
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
        fseek($handle, 3);
    }
    
    // 读取所有行
    $allLines = [];
    while (($line = fgets($handle)) !== false) {
        $currentLine++;
        $line = trim($line);
        
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        
        $link = '';
        $name = '';
        $isMagnet = false;
        $isEd2k = false;
        
        if (isMagnetLink($line)) {
            $link = $line;
            $name = getFileNameFromMagnet($link);
            $isMagnet = true;
        }
        elseif (isEd2kLink($line)) {
            $link = $line;
            $name = getFileNameFromEd2k($link);
            $isEd2k = true;
        }
        else {
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
                $namePart = trim(substr($line, 0, $separatorPos));
                $linkPart = trim(substr($line, $separatorPos + 1));
                
                if (isMagnetLink($linkPart)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);
                    $isMagnet = true;
                } elseif (isEd2kLink($linkPart)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);
                    $isEd2k = true;
                } elseif (filter_var($linkPart, FILTER_VALIDATE_URL)) {
                    $link = $linkPart;
                    $name = !empty($namePart) ? $namePart : 'Online Video';
                }
            } else {
                // 如果没有分隔符，整行作为链接，自动生成名称
                if (isMagnetLink($line)) {
                    $link = $line;
                    $name = getFileNameFromMagnet($line);
                    $isMagnet = true;
                } elseif (isEd2kLink($line)) {
                    $link = $line;
                    $name = getFileNameFromEd2k($line);
                    $isEd2k = true;
                } elseif (filter_var($line, FILTER_VALIDATE_URL)) {
                    $link = $line;
                    $name = 'Online Video';
                }
            }
        }
        
        if (!empty($link) && !empty($name)) {
            $allLines[] = [
                'name' => $name,
                'link' => $link,
                'is_magnet' => $isMagnet,
                'is_ed2k' => $isEd2k,
                'line_number' => $currentLine
            ];
        }
    }
    
    fclose($handle);
    
    if (empty($allLines)) {
        return null;
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    $imgIndex = 0 % count($defaultImages);
    
    // 构建播放列表 - 使用实际名字作为线路名称
    $playUrls = [];
    foreach ($allLines as $index => $lineData) {
        $playSource = $lineData['name'];
        // 如果是磁力链接或电驴链接，在名称后添加类型标识
        if ($lineData['is_magnet']) {
            $playSource = $lineData['name'] . ' [磁力]';
        } elseif ($lineData['is_ed2k']) {
            $playSource = $lineData['name'] . ' [电驴]';
        }
        
        $playUrls[] = $playSource . '$' . $lineData['link'];
    }
    
    $playUrlStr = implode('#', $playUrls);
    
    $video = [
        'vod_id' => 'txt_aggregated_' . md5($filePath),
        'vod_name' => '[聚合] ' . $fileName,
        'vod_pic' => $defaultImages[$imgIndex],
        'vod_remarks' => count($allLines) . '个资源',
        'vod_year' => date('Y'),
        'vod_area' => 'China',
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allLines) . " 个资源\n文件路径: " . $filePath . "\n【聚合模式】所有资源合并到一个项目中",
        'vod_play_from' => '资源列表',
        'vod_play_url' => $playUrlStr,
        'is_aggregated' => true
    ];
    
    return $video;
}

// 查找单资源TXT视频
function findSingleTxtVideoByLine($filePath, $targetLine) {
    if (!file_exists($filePath)) {
        return null;
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    
    $currentLine = 0;
    $video = null;
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    // 处理BOM头
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
        fseek($handle, 3);
    }
    
    while (($line = fgets($handle)) !== false) {
        $currentLine++;
        $line = trim($line);
        
        // 跳过空行和注释行
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || $line[0] === '//') {
            continue;
        }
        
        if ($currentLine == $targetLine) {
            $link = '';
            $name = '';
            $isMagnet = false;
            $isEd2k = false;
            
            // 使用与parseTxtFile相同的解析逻辑
            if (isMagnetLink($line)) {
                $link = $line;
                $name = getFileNameFromMagnet($line);
                $isMagnet = true;
            }
            elseif (isEd2kLink($line)) {
                $link = $line;
                $name = getFileNameFromEd2k($link);
                $isEd2k = true;
            }
            else {
                $separators = [',', "\t", '|', '$', '#', ';', '：', ' '];
                $separatorPos = false;
                $usedSeparator = '';
                
                foreach ($separators as $sep) {
                    $pos = strpos($line, $sep);
                    if ($pos !== false) {
                        $separatorPos = $pos;
                        $usedSeparator = $sep;
                        break;
                    }
                }
                
                if ($separatorPos !== false) {
                    $namePart = trim(substr($line, 0, $separatorPos));
                    $linkPart = trim(substr($line, $separatorPos + strlen($usedSeparator)));
                    
                    if (isMagnetLink($linkPart)) {
                        $link = $linkPart;
                        $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);
                        $isMagnet = true;
                    } elseif (isEd2kLink($linkPart)) {
                        $link = $linkPart;
                        $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);
                        $isEd2k = true;
                    } elseif (isValidLink($linkPart)) {
                        $link = $linkPart;
                        $name = !empty($namePart) ? $namePart : 'Online Video';
                    }
                } else {
                    if (isValidLink($line)) {
                        $link = $line;
                        $name = 'Online Video';
                    }
                }
            }
            
            if (!empty($link) && !empty($name) && isValidLink($link)) {
                $imgIndex = $currentLine % count($defaultImages);
                
                $playSource = 'Online';
                if ($isMagnet) {
                    $playSource = 'Magnet';
                } elseif ($isEd2k) {
                    $playSource = 'Ed2k';
                }
                
                // 生成有意义的选集名称
                $episodeName = generateEpisodeName($name, $isMagnet, $isEd2k);
                
                $video = [
                    'vod_id' => 'txt_single_' . md5($filePath) . '_' . $currentLine,
                    'vod_name' => $name,
                    'vod_pic' => $defaultImages[$imgIndex],
                    'vod_remarks' => $isMagnet ? 'Magnet' : ($isEd2k ? 'Ed2k' : 'HD'),
                    'vod_year' => date('Y'),
                    'vod_area' => 'China',
                    'vod_content' => $name . ' - 来自TXT文件的资源',
                    'vod_play_from' => $playSource,
                    'vod_play_url' => $episodeName . '$' . $link,
                    'is_single' => true
                ];
            }
            break;
        }
    }
    
    fclose($handle);
    return $video;
}

// 生成选集名称的辅助函数
function generateEpisodeName($resourceName, $isMagnet, $isEd2k) {
    // 添加类型图标
    $icon = '';
    if ($isMagnet) {
        $icon = '🧲';
    } elseif ($isEd2k) {
        $icon = '⚡';
    } else {
        $icon = '🌐';
    }
    
    // 简化资源名称（如果太长）
    $displayName = $resourceName;
    if (strlen($resourceName) > 20) {
        $displayName = mb_substr($resourceName, 0, 18, 'UTF-8') . '...';
    }
    
    return $displayName . ' ' . $icon;
}
// ==================== 第十三部分：M3U查找函数和格式化函数 ====================

// 查找聚合M3U视频详情
function findM3uVideoByLine($filePath, $targetLine) {
    if (!file_exists($filePath)) {
        return null;
    }
    
    $handle = @fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }
    
    $currentLine = 0;
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
        fseek($handle, 3);
    }
    
    // 读取所有频道
    $allChannels = [];
    $currentName = '';
    $currentIcon = '';
    $currentGroup = '';
    
    while (($line = fgets($handle)) !== false) {
        $currentLine++;
        $line = trim($line);
        if ($line === '') continue;
        
        if (strpos($line, '#EXTM3U') === 0) {
            continue;
        }
        
        if (strpos($line, '#EXTINF:') === 0) {
            $currentName = '';
            $currentIcon = '';
            $currentGroup = '';
            
            $parts = explode(',', $line, 2);
            if (count($parts) > 1) {
                $currentName = trim($parts[1]);
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {
                $currentIcon = trim($iconMatches[1]);
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {
                $currentGroup = trim($groupMatches[1]);
            }
            continue;
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];
        $hasValidProtocol = false;
        foreach ($validProtocols as $protocol) {
            if (stripos($line, $protocol) === 0) {
                $hasValidProtocol = true;
                break;
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {
            $allChannels[] = [
                'name' => $currentName,
                'url' => $line,
                'icon' => $currentIcon,
                'group' => $currentGroup,
                'line_number' => $currentLine
            ];
            
            $currentName = '';
            $currentIcon = '';
            $currentGroup = '';
        }
    }
    
    fclose($handle);
    
    if (empty($allChannels)) {
        return null;
    }
    
    // 构建播放列表 - 使用频道名称作为线路名称
    $playUrls = [];
    foreach ($allChannels as $index => $channelData) {
        $playSource = $channelData['name'];
        // 如果有分组信息，添加到名称中
        if (!empty($channelData['group'])) {
            $playSource = $channelData['name'] . ' [' . $channelData['group'] . ']';
        }
        
        $playUrls[] = $playSource . '$' . $channelData['url'];
    }
    
    $playUrlStr = implode('#', $playUrls);
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    $imgIndex = 0 % count($defaultImages);
    
    $video = [
        'vod_id' => 'm3u_aggregated_' . md5($filePath),
        'vod_name' => '[聚合] ' . $fileName,
        'vod_pic' => $defaultImages[$imgIndex],
        'vod_remarks' => count($allChannels) . '个频道',
        'vod_year' => date('Y'),
        'vod_area' => 'China',
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allChannels) . " 个电视频道\n文件路径: " . $filePath . "\n【聚合模式】所有频道合并到一个项目中",
        'vod_play_from' => '频道列表',
        'vod_play_url' => $playUrlStr,
        'is_aggregated' => true
    ];
    
    return $video;
}

// 查找单资源M3U视频
function findSingleM3uVideoByLine($filePath, $targetLine, $resourceName = '正片') {
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
    $currentIcon = '';
    $currentGroup = '';
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    // 从文件路径获取文件名（不含扩展名）作为播放源名称
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);
    $playSource = $fileName ?: 'Live'; // 使用文件名作为播放源
    
    $firstLine = fgets($handle);
    rewind($handle);
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");
    if ($hasBOM) {
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
            $currentIcon = '';
            $currentGroup = '';
            
            $parts = explode(',', $line, 2);
            if (count($parts) > 1) {
                $currentName = trim($parts[1]);
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {
                $currentIcon = trim($iconMatches[1]);
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {
                $currentGroup = trim($groupMatches[1]);
            }
            continue;
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];
        $hasValidProtocol = false;
        foreach ($validProtocols as $protocol) {
            if (stripos($line, $protocol) === 0) {
                $hasValidProtocol = true;
                break;
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {
            if ($currentLine == $targetLine) {
                $imgIndex = $currentLine % count($defaultImages);
                
                $videoCover = $currentIcon;
                if (empty($videoCover) || !filter_var($videoCover, FILTER_VALIDATE_URL)) {
                    $videoCover = $defaultImages[$imgIndex];
                }
                
                // 选集名称使用资源名称
                $episodeName = $resourceName;
                
                $video = [
                    'vod_id' => 'm3u_single_' . md5($filePath) . '_' . $currentLine,
                    'vod_name' => $currentName,
                    'vod_pic' => $videoCover,
                    'vod_remarks' => 'Live',
                    'vod_year' => date('Y'),
                    'vod_area' => 'China',
                    'vod_content' => $currentName . ' live channel',
                    'vod_play_from' => $playSource, // 线路名称：来自文件名
                    'vod_play_url' => $episodeName . '$' . $line, // 选集：来自视频资源名字
                    'is_single' => true
                ];
                break;
            }
            
            $currentName = '';
            $currentIcon = '';
            $currentGroup = '';
        }
    }
    
    fclose($handle);
    return $video;
}

// 查找分类视频详情
function findCategoryVideoById($videoID) {
    $allFiles = getAllFiles();
    
    foreach ($allFiles as $file) {
        if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3'])) {
            if (!file_exists($file['path']) || !extension_loaded('pdo_sqlite')) {
                continue;
            }
            
            try {
                $db = new PDO("sqlite:" . $file['path']);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('videos', $tables) && in_array('categories', $tables)) {
                    $querySQL = "SELECT v.*, c.name as category_name FROM videos v LEFT JOIN categories c ON v.category_id = c.id WHERE v.id = ?";
                    $stmt = $db->prepare($querySQL);
                    $stmt->execute([$videoID]);
                    $videoData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($videoData) {
                        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
                        
                        $playSource = 'Video';
                        $playUrl = $videoData['play_url'] ?? '';
                        
                        if (strpos($playUrl, 'magnet:') === 0) {
                            $playSource = 'Magnet';
                        } elseif (strpos($playUrl, 'ed2k://') === 0) {
                            $playSource = 'Ed2k';
                        }
                        
                        $video = [
                            'vod_id' => 'video_' . $videoData['id'],
                            'vod_name' => $videoData['name'] ?? 'Unknown Video',
                            'vod_pic' => $videoData['image'] ?? $defaultImages[0],
                            'vod_remarks' => $videoData['remarks'] ?? 'HD',
                            'vod_year' => $videoData['year'] ?? '',
                            'vod_area' => $videoData['area'] ?? 'China',
                            'vod_actor' => $videoData['actor'] ?? '',
                            'vod_director' => $videoData['director'] ?? '',
                            'vod_content' => $videoData['content'] ?? ($videoData['name'] ?? 'Unknown Video') . ' content',
                            'vod_play_from' => $playSource . ' · ' . ($videoData['category_name'] ?? 'Unknown Category'),
                            'vod_play_url' => 'Play$' . $playUrl
                        ];
                        
                        $db = null;
                        return $video;
                    }
                }
                
                $db = null;
            } catch (PDOException $e) {
                continue;
            }
        }
    }
    
    return null;
}

// 查找数据库视频详情
function findDatabaseVideoByIndex($filePath, $tableName, $videoIndex) {
    if (!file_exists($filePath) || !extension_loaded('pdo_sqlite')) {
        return null;
    }
    
    try {
        $db = new PDO("sqlite:" . $filePath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 首先检查表结构，确定是否有data字段
        $fieldInfo = $db->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);
        $fieldNames = array_column($fieldInfo, 'name');
        
        $hasDataField = in_array('data', $fieldNames);
        
        if ($hasDataField) {
            // 如果有data字段，解析JSON数据
            $querySQL = "SELECT data FROM $tableName LIMIT 1 OFFSET " . intval($videoIndex);
            $stmt = $db->query($querySQL);
            $jsonData = $stmt->fetch(PDO::FETCH_COLUMN);
            
            if ($jsonData) {
                $videoData = json_decode($jsonData, true);
                if ($videoData && is_array($videoData)) {
                    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
                    
                    $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';
                    $videoLink = '';
                    $playSource = 'Database';
                    
                    // 优先使用磁力链接
                    if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {
                        $videoLink = $videoData['magnet'];
                        $playSource = 'Magnet';
                    } 
                    // 其次使用torrent链接
                    elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {
                        $videoLink = $videoData['torrent'];
                        $playSource = 'Torrent';
                    }
                    // 最后使用普通链接
                    elseif (isset($videoData['link']) && !empty($videoData['link'])) {
                        $videoLink = $videoData['link'];
                        if (strpos($videoLink, 'magnet:') === 0) {
                            $playSource = 'Magnet';
                        } elseif (strpos($videoLink, 'ed2k://') === 0) {
                            $playSource = 'Ed2k';
                        }
                    }
                    
                    if (empty($videoLink)) {
                        $db = null;
                        return null;
                    }
                    
                    // 提取其他信息
                    $videoCover = $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[intval($videoIndex) % count($defaultImages)];
                    $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content';
                    $videoYear = $videoData['year'] ?? '';
                    $videoArea = $videoData['area'] ?? $videoData['region'] ?? 'International';
                    $videoSize = $videoData['size'] ?? '';
                    $uploader = $videoData['uploader'] ?? '';
                    
                    // 构建内容描述
                    $content = $videoDesc;
                    if (!empty($uploader)) {
                        $content .= "\n上传者: " . $uploader;
                    }
                    if (!empty($videoSize)) {
                        $content .= "\n大小: " . $videoSize;
                    }
                    if (isset($videoData['imdb']) && !empty($videoData['imdb'])) {
                        $content .= "\nIMDb: " . $videoData['imdb'];
                    }
                    
                    $video = [
                        'vod_id' => 'json_db_' . md5($filePath) . '_' . $tableName . '_' . $videoIndex,
                        'vod_name' => $videoName,
                        'vod_pic' => $videoCover,
                        'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',
                        'vod_year' => $videoYear,
                        'vod_area' => $videoArea,
                        'vod_content' => $content,
                        'vod_play_from' => $playSource,
                        'vod_play_url' => 'Play$' . $videoLink
                    ];
                    
                    $db = null;
                    return $video;
                }
            }
        } else {
            // 如果没有data字段，使用通用查询
            $querySQL = "SELECT * FROM $tableName LIMIT 1 OFFSET " . intval($videoIndex);
            $stmt = $db->query($querySQL);
            $rowData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rowData) {
                $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
                
                $videoName = $rowData['name'] ?? $rowData['title'] ?? $rowData['vod_name'] ?? 'Unknown Video';
                $videoUrl = $rowData['magnet'] ?? $rowData['url'] ?? $rowData['link'] ?? $rowData['play_url'] ?? '';
                $playSource = 'Database';
                
                if (strpos($videoUrl, 'magnet:') === 0) {
                    $playSource = 'Magnet';
                } elseif (strpos($videoUrl, 'ed2k://') === 0) {
                    $playSource = 'Ed2k';
                }
                
                if (empty($videoUrl)) {
                    $db = null;
                    return null;
                }
                
                $videoCover = $rowData['image'] ?? $rowData['pic'] ?? $rowData['cover'] ?? $rowData['vod_pic'] ?? $defaultImages[intval($videoIndex) % count($defaultImages)];
                $videoDesc = $rowData['content'] ?? $rowData['desc'] ?? $rowData['description'] ?? $rowData['vod_content'] ?? $videoName . ' content';
                $videoYear = $rowData['year'] ?? $rowData['vod_year'] ?? date('Y');
                $videoArea = $rowData['area'] ?? $rowData['region'] ?? $rowData['vod_area'] ?? 'China';
                
                $video = [
                    'vod_id' => 'db_' . md5($filePath) . '_' . $tableName . '_' . $videoIndex,
                    'vod_name' => $videoName,
                    'vod_pic' => $videoCover,
                    'vod_remarks' => 'HD',
                    'vod_year' => $videoYear,
                    'vod_area' => $videoArea,
                    'vod_content' => $videoDesc,
                    'vod_play_from' => $playSource,
                    'vod_play_url' => 'Play$' . $videoUrl
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

// 格式化视频详情
function formatVideoDetail($video) {
    return [
        'vod_id' => $video['vod_id'] ?? '',
        'vod_name' => $video['vod_name'] ?? 'Unknown Video',
        'vod_pic' => $video['vod_pic'] ?? 'https://www.252035.xyz/imgs?t=1335527662',
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',
        'vod_year' => $video['vod_year'] ?? '',
        'vod_area' => $video['vod_area'] ?? 'China',
        'vod_director' => $video['vod_director'] ?? '',
        'vod_actor' => $video['vod_actor'] ?? '',
        'vod_content' => $video['vod_content'] ?? 'Video detail content',
        'vod_play_from' => $video['vod_play_from'] ?? 'default',
        'vod_play_url' => $video['vod_play_url'] ?? ''
    ];
}
// ==================== 第十四部分：媒体聚合项目和文件结束 ====================

// 创建媒体聚合项目
function createMediaAggregatedProjects($videoFiles, $audioFiles) {
    $aggregatedProjects = [];
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    // 视频聚合项目
    if (!empty($videoFiles)) {
        $playUrls = [];
        foreach ($videoFiles as $index => $file) {
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            $playSource = '视频' . ($index + 1);
            $playUrls[] = $playSource . '$' . urlencode($file['path']);
        }
        
        $playUrlStr = implode('#', $playUrls);
        
        $aggregatedProjects[] = [
            'vod_id' => 'media_aggregated_video',
            'vod_name' => '[聚合] 所有视频文件',
            'vod_pic' => $defaultImages[0],
            'vod_remarks' => count($videoFiles) . '个视频',
            'vod_year' => date('Y'),
            'vod_area' => '本地文件',
            'vod_content' => '聚合所有视频文件，共 ' . count($videoFiles) . ' 个视频文件',
            'vod_play_from' => '视频列表',
            'vod_play_url' => $playUrlStr,
            'is_aggregated' => true,
            'media_type' => 'video_aggregated'
        ];
    }
    
    // 音频聚合项目
    if (!empty($audioFiles)) {
        $playUrls = [];
        foreach ($audioFiles as $index => $file) {
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';
            $playSource = '音频' . ($index + 1);
            $playUrls[] = $playSource . '$' . urlencode($file['path']);
        }
        
        $playUrlStr = implode('#', $playUrls);
        
        $aggregatedProjects[] = [
            'vod_id' => 'media_aggregated_audio',
            'vod_name' => '[聚合] 所有音频文件',
            'vod_pic' => $defaultImages[1 % count($defaultImages)],
            'vod_remarks' => count($audioFiles) . '个音频',
            'vod_year' => date('Y'),
            'vod_area' => '本地文件',
            'vod_content' => '聚合所有音频文件，共 ' . count($audioFiles) . ' 个音频文件',
            'vod_play_from' => '音频列表',
            'vod_play_url' => $playUrlStr,
            'is_aggregated' => true,
            'media_type' => 'audio_aggregated'
        ];
    }
    
    // 全部媒体聚合项目
    if (!empty($videoFiles) && !empty($audioFiles)) {
        $playUrls = [];
        $itemIndex = 1;
        
        // 添加视频文件
        foreach ($videoFiles as $file) {
            $playSource = '视频' . $itemIndex;
            $playUrls[] = $playSource . '$' . urlencode($file['path']);
            $itemIndex++;
        }
        
        // 添加音频文件
        foreach ($audioFiles as $file) {
            $playSource = '音频' . $itemIndex;
            $playUrls[] = $playSource . '$' . urlencode($file['path']);
            $itemIndex++;
        }
        
        $playUrlStr = implode('#', $playUrls);
        
        $aggregatedProjects[] = [
            'vod_id' => 'media_aggregated_all',
            'vod_name' => '[聚合] 所有媒体文件',
            'vod_pic' => $defaultImages[2 % count($defaultImages)],
            'vod_remarks' => (count($videoFiles) + count($audioFiles)) . '个文件',
            'vod_year' => date('Y'),
            'vod_area' => '本地文件',
            'vod_content' => '聚合所有媒体文件，共 ' . count($videoFiles) . ' 个视频 + ' . count($audioFiles) . ' 个音频',
            'vod_play_from' => '媒体列表',
            'vod_play_url' => $playUrlStr,
            'is_aggregated' => true,
            'media_type' => 'all_aggregated'
        ];
    }
    
    return $aggregatedProjects;
}
?>