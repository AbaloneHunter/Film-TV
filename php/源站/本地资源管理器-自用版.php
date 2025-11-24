<?php
// ==================== 第一部分：基础配置和常量定义 ====================
ini_set('memory_limit', '-1');  // 设置内存限制为无限制
@set_time_limit(300);  // 设置脚本执行时间限制为300秒
// 基础扫描路径 - 媒体文件和数据库文件的根目录
define('BASE_SCAN_PATH', '/storage/emulated/0/lg/autoloader/php');  // 自定义基础扫描路径常量
// 最大扫描深度 - 文件夹递归扫描的最大层级，防止无限递归
define('MAX_SCAN_DEPTH', 50);  // 定义最大扫描深度常量
// 数据库兼容模式 - 是否启用数据库兼容性处理
define('DB_COMPAT_MODE', true);  // 定义数据库兼容模式常量
// 最大数据库结果数 - 从单个数据库表中读取的最大记录数量
define('MAX_DB_RESULTS', 5000);  // 定义最大数据库结果数常量
// 数据库扫描深度 - 数据库文件扫描的深度限制
define('DB_SCAN_DEPTH', 10000);  // 定义数据库扫描深度常量
// 显示模式配置
define('DISPLAY_MODE', 'both');  // 定义显示模式常量：聚合模式、单资源模式或两者都显示

$SUPPORTED_DB_TABLES = [  // 定义支持的数据库表名模式
    'video' => '/^(videos?|film|movie|tv|series|影视|视频)/i',  // 视频表正则模式
    'category' => '/^(categor(y|ies)|type|分类|类型)/i',  // 分类表正则模式
    'magnet' => '/^(magnet|bt|torrent|种子|磁力)/i',  // 磁力表正则模式
    'channel' => '/^(channel|tv_channel|live|频道|直播)/i',  // 频道表正则模式
    'uploader' => '/^(uploader|user|上传者)/i'  // 上传者表正则模式
];

$DB_FIELD_MAPPING = [  // 定义数据库字段映射关系
    'id' => ['id', 'vid', 'video_id', 'film_id'],  // ID字段的可能名称
    'name' => ['name', 'title', 'video_name', 'film_name', 'vod_name'],  // 名称字段的可能名称
    'url' => ['url', 'link', 'play_url', 'video_url', 'vod_url'],  // URL字段的可能名称
    'magnet' => ['magnet', 'magnet_url', 'magnet_link', 'bt_url'],  // 磁力字段的可能名称
    'image' => ['image', 'pic', 'cover', 'poster', 'vod_pic'],  // 图片字段的可能名称
    'category' => ['category', 'type', 'class', 'vod_type'],  // 分类字段的可能名称
    'year' => ['year', 'vod_year'],  // 年份字段的可能名称
    'area' => ['area', 'region', 'vod_area'],  // 地区字段的可能名称
    'actor' => ['actor', 'star', 'vod_actor'],  // 演员字段的可能名称
    'director' => ['director', 'vod_director'],  // 导演字段的可能名称
    'content' => ['content', 'desc', 'description', 'vod_content'],  // 内容字段的可能名称
    'data' => ['data', 'json_data', 'info']  // 数据字段的可能名称
];

// 支持的媒体文件扩展名
$MEDIA_EXTENSIONS = [  // 定义支持的媒体文件扩展名
    'video' => ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'mpeg', 'mpg'],  // 视频文件扩展名
    'audio' => ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma']  // 音频文件扩展名
];

header('Content-Type: application/json; charset=utf-8');  // 设置响应头为JSON格式
// ==================== 第二部分：请求参数处理和路由分发 ====================
$ac = $_GET['ac'] ?? 'detail';  // 获取动作参数，默认为detail
$t = $_GET['t'] ?? '';  // 获取分类参数
$pg = $_GET['pg'] ?? '1';  // 获取页码参数，默认为1
$ids = $_GET['ids'] ?? '';  // 获取ID参数
$wd = $_GET['wd'] ?? '';  // 获取搜索关键词参数
$flag = $_GET['flag'] ?? '';  // 获取标志参数
$id = $_GET['id'] ?? '';  // 获取ID参数
$play = $_GET['play'] ?? '';  // 获取播放参数

switch ($ac) {  // 根据动作参数分发到不同函数
    case 'detail':  // 详情页请求
        if (!empty($ids)) {  // 如果有ID参数
            $result = getDetail($ids);  // 获取详情
        } elseif (!empty($t)) {  // 如果有分类参数
            $result = getCategory($t, $pg);  // 获取分类内容
        } else {  // 默认情况
            $result = getHome();  // 获取首页
        }
        break;
    
    case 'search':  // 搜索请求
        $result = search($wd, $pg);  // 执行搜索
        break;
        
    case 'play':  // 播放请求
        $result = getPlay($flag, $id);  // 获取播放信息
        break;
    
    default:  // 未知动作
        $result = ['error' => 'Unknown action: ' . $ac];  // 返回错误信息
}

if (!empty($play)) {  // 如果有直接播放参数
    $result = directPlayUrl($play);  // 获取直接播放URL
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);  // 输出JSON格式结果
// ==================== 第三部分：链接处理和验证函数 ====================

// 修复：增强磁力链接识别
function isMagnetLink($link) {  // 判断是否为磁力链接
    if (empty($link)) return false;  // 空链接返回false
    
    // 更全面的磁力链接识别
    $magnetPatterns = [  // 磁力链接正则模式数组
        '/^magnet:\?xt=urn:btih:([a-zA-Z0-9]{32,40})/i',  // 标准磁力链接模式
        '/^magnet:\?xt=urn:btih:([a-zA-Z0-9]{32})/i',  // 32位哈希磁力链接模式
        '/^magnet:\?xt=urn:sha1:([a-zA-Z0-9]{40})/i',  // SHA1磁力链接模式
        '/^magnet:\?dn=.*&xt=urn:btih:/i'  // 带名称的磁力链接模式
    ];
    
    foreach ($magnetPatterns as $pattern) {  // 遍历所有模式
        if (preg_match($pattern, $link)) {  // 如果匹配成功
            return true;  // 返回true
        }
    }
    
    // 基础检查
    if (strpos($link, 'magnet:?xt=urn:btih:') === 0) {  // 检查标准磁力链接前缀
        return true;  // 返回true
    }
    if (strpos($link, 'magnet:?xt=urn:sha1:') === 0) {  // 检查SHA1磁力链接前缀
        return true;  // 返回true
    }
    
    return false;  // 不匹配任何模式返回false
}

// 修复：增强电驴链接识别
function isEd2kLink($link) {  // 判断是否为电驴链接
    if (empty($link)) return false;  // 空链接返回false
    
    $ed2kPatterns = [  // 电驴链接正则模式数组
        '/^ed2k:\/\/\|file\|[^\|]+\|\d+\|([a-fA-F0-9]{32})\|/',  // 标准电驴链接模式
        '/^ed2k:\/\/\|file\|[^\|]+\|\d+\|\//'  // 简化电驴链接模式
    ];
    
    foreach ($ed2kPatterns as $pattern) {  // 遍历所有模式
        if (preg_match($pattern, $link)) {  // 如果匹配成功
            return true;  // 返回true
        }
    }
    
    if (strpos($link, 'ed2k://|file|') === 0) {  // 检查电驴链接前缀
        return true;  // 返回true
    }
    
    return false;  // 不匹配任何模式返回false
}

// 修复：新增链接验证函数
function isValidLink($link) {  // 验证链接是否有效
    if (empty($link)) return false;  // 空链接返回false
    
    // 支持的协议列表（大幅扩展）
    $validProtocols = [  // 有效协议数组
        'http://', 'https://', 'rtmp://', 'rtsp://', 'udp://',  // 常见网络协议
        'magnet:', 'ed2k://', 'ftp://', 'ftps://', 'sftp://',  // P2P和文件传输协议
        'thunder://', 'flashget://', 'qqdl://'  // 下载工具协议
    ];
    
    foreach ($validProtocols as $protocol) {  // 遍历所有协议
        if (stripos($link, $protocol) === 0) {  // 检查链接是否以协议开头
            return true;  // 返回true
        }
    }
    
    // 允许没有协议但包含常见域名的链接
    $commonDomains = ['.com', '.org', '.net', '.tv', '.cc', '.me', '.io'];  // 常见域名后缀
    foreach ($commonDomains as $domain) {  // 遍历所有域名后缀
        if (stripos($link, $domain) !== false) {  // 检查链接是否包含域名
            return true;  // 返回true
        }
    }
    
    return false;  // 不匹配任何条件返回false
}

// 修复：增强磁力链接文件名提取
function getFileNameFromMagnet($magnetLink) {  // 从磁力链接提取文件名
    // 尝试从dn参数提取文件名
    if (preg_match('/&dn=([^&]+)/i', $magnetLink, $matches)) {  // 匹配dn参数
        $filename = urldecode($matches[1]);  // URL解码文件名
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);  // 替换非法字符
        return $filename ?: 'Magnet Resource';  // 返回文件名或默认值
    }
    
    // 尝试其他常见参数
    if (preg_match('/&tr=[^&]*&dn=([^&]+)/i', $magnetLink, $matches)) {  // 匹配带tr参数的dn
        $filename = urldecode($matches[1]);  // URL解码文件名
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);  // 替换非法字符
        return $filename ?: 'Magnet Resource';  // 返回文件名或默认值
    }
    
    // 从xt参数提取哈希值作为备用名称
    if (preg_match('/xt=urn:btih:([a-zA-Z0-9]{32,40})/i', $magnetLink, $matches)) {  // 匹配xt参数
        $hash = strtoupper($matches[1]);  // 转换为大写
        return 'Magnet_' . substr($hash, 0, 8);  // 返回带哈希的文件名
    }
    
    return 'Magnet Resource';  // 返回默认文件名
}

// 修复：增强电驴链接文件名提取
function getFileNameFromEd2k($ed2kLink) {  // 从电驴链接提取文件名
    if (preg_match('/\|file\|([^\|]+)\|/i', $ed2kLink, $matches)) {  // 匹配文件名部分
        $filename = urldecode($matches[1]);  // URL解码文件名
        $filename = preg_replace('/[<>:"\/\\|?*]/', '_', $filename);  // 替换非法字符
        return $filename ?: 'Ed2k Resource';  // 返回文件名或默认值
    }
    
    return 'Ed2k Resource';  // 返回默认文件名
}
// ==================== 第四部分：TXT文件解析功能 ====================

// 修复：增强TXT文件解析主函数
function parseTxtFile($filePath) {  // 解析TXT文件
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return [];  // 文件不存在返回空数组
    }
    
    $handle = @fopen($filePath, 'r');  // 尝试打开文件
    if (!$handle) {  // 如果打开失败
        return [];  // 返回空数组
    }
    
    $videoList = [];  // 初始化视频列表
    $lineNumber = 0;  // 初始化行号计数器
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    // 处理BOM头
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查是否有BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    // 读取所有行并解析
    $allLines = [];  // 初始化所有行数组
    while (($line = fgets($handle)) !== false) {  // 逐行读取文件
        $lineNumber++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        
        // 跳过空行和注释行
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || $line[0] === '//') {  // 检查空行或注释
            continue;  // 跳过当前行
        }
        
        $link = '';  // 初始化链接变量
        $name = '';  // 初始化名称变量
        $isMagnet = false;  // 初始化磁力链接标志
        $isEd2k = false;  // 初始化电驴链接标志
        
        // 首先检查是否是纯磁力链接或电驴链接
        if (isMagnetLink($line)) {  // 检查是否为磁力链接
            $link = $line;  // 设置链接
            $name = getFileNameFromMagnet($line);  // 从磁力链接提取文件名
            $isMagnet = true;  // 设置磁力链接标志
        }
        elseif (isEd2kLink($line)) {  // 检查是否为电驴链接
            $link = $line;  // 设置链接
            $name = getFileNameFromEd2k($line);  // 从电驴链接提取文件名
            $isEd2k = true;  // 设置电驴链接标志
        }
        else {  // 不是纯链接
            // 尝试使用分隔符分割名称和链接
            $separators = [',', "\t", '|', '$', '#', ';', '：', ' '];  // 分隔符数组
            $separatorPos = false;  // 初始化分隔符位置
            $usedSeparator = '';  // 初始化使用的分隔符
            
            foreach ($separators as $sep) {  // 遍历所有分隔符
                $pos = strpos($line, $sep);  // 查找分隔符位置
                if ($pos !== false) {  // 如果找到分隔符
                    $separatorPos = $pos;  // 设置分隔符位置
                    $usedSeparator = $sep;  // 设置使用的分隔符
                    break;  // 跳出循环
                }
            }
            
            if ($separatorPos !== false) {  // 如果找到分隔符
                $namePart = trim(substr($line, 0, $separatorPos));  // 提取名称部分
                $linkPart = trim(substr($line, $separatorPos + strlen($usedSeparator)));  // 提取链接部分
                
                // 验证链接部分
                if (isMagnetLink($linkPart)) {  // 检查链接部分是否为磁力链接
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);  // 设置名称
                    $isMagnet = true;  // 设置磁力链接标志
                } elseif (isEd2kLink($linkPart)) {  // 检查链接部分是否为电驴链接
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);  // 设置名称
                    $isEd2k = true;  // 设置电驴链接标志
                } elseif (isValidLink($linkPart)) {  // 检查链接部分是否有效
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : 'Online Video';  // 设置名称
                } else {  // 链接部分无效
                    // 如果链接部分无效，尝试整行作为链接
                    if (isValidLink($line)) {  // 检查整行是否为有效链接
                        $link = $line;  // 设置链接
                        $name = 'Online Video';  // 设置默认名称
                    }
                }
            } else {  // 没有分隔符
                // 如果没有分隔符，整行作为链接
                if (isMagnetLink($line)) {  // 检查整行是否为磁力链接
                    $link = $line;  // 设置链接
                    $name = getFileNameFromMagnet($line);  // 从磁力链接提取文件名
                    $isMagnet = true;  // 设置磁力链接标志
                } elseif (isEd2kLink($line)) {  // 检查整行是否为电驴链接
                    $link = $line;  // 设置链接
                    $name = getFileNameFromEd2k($line);  // 从电驴链接提取文件名
                    $isEd2k = true;  // 设置电驴链接标志
                } elseif (isValidLink($line)) {  // 检查整行是否为有效链接
                    $link = $line;  // 设置链接
                    $name = 'Online Video';  // 设置默认名称
                }
            }
        }
        
        // 最终验证链接和名称
        if (!empty($link) && !empty($name) && isValidLink($link)) {  // 验证链接和名称
            $allLines[] = [  // 添加到所有行数组
                'name' => $name,  // 名称
                'link' => $link,  // 链接
                'is_magnet' => $isMagnet,  // 磁力链接标志
                'is_ed2k' => $isEd2k,  // 电驴链接标志
                'line_number' => $lineNumber  // 行号
            ];
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    
    if (empty($allLines)) {  // 如果没有有效行
        return [];  // 返回空数组
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);  // 获取文件名（不含扩展名）
    
    // 根据显示模式决定返回内容
    switch (DISPLAY_MODE) {  // 根据显示模式选择
        case 'aggregated':  // 聚合模式
            // 聚合模式：只返回一个聚合项目
            return getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages);  // 返回聚合视频
            
        case 'single':  // 单资源模式
            // 单资源模式：返回所有单独的资源
            return getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages);  // 返回单资源视频
            
        case 'both':  // 两种模式
        default:  // 默认情况
            // 两种模式都显示：先显示聚合项目，再显示单个资源
            $aggregated = getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages);  // 获取聚合视频
            $single = getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages);  // 获取单资源视频
            return array_merge($aggregated, $single);  // 合并两种模式的结果
    }
}

// 获取聚合模式的TXT视频
function getAggregatedTxtVideo($filePath, $fileName, $allLines, $defaultImages) {  // 获取聚合TXT视频
    $videoList = [];  // 初始化视频列表
    
    $imgIndex = 0 % count($defaultImages);  // 计算图片索引
    
    // 构建播放列表 - 使用实际名字作为线路名称
    $playUrls = [];  // 初始化播放URL数组
    foreach ($allLines as $index => $lineData) {  // 遍历所有行数据
        $playSource = $lineData['name'];  // 获取播放源名称
        // 如果是磁力链接或电驴链接，在名称后添加类型标识
        if ($lineData['is_magnet']) {  // 如果是磁力链接
            $playSource = $lineData['name'] . ' [磁力]';  // 添加磁力标识
        } elseif ($lineData['is_ed2k']) {  // 如果是电驴链接
            $playSource = $lineData['name'] . ' [电驴]';  // 添加电驴标识
        }
        
        $playUrls[] = $playSource . '$' . $lineData['link'];  // 添加到播放URL数组
    }
    
    $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
    
    // 创建单个视频项目，包含所有线路
    $videoList[] = [  // 添加到视频列表
        'vod_id' => 'txt_aggregated_' . md5($filePath),  // 视频ID（文件路径MD5）
        'vod_name' => '[聚合] ' . $fileName,  // 视频名称
        'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
        'vod_remarks' => count($allLines) . '个资源',  // 视频备注
        'vod_year' => date('Y'),  // 视频年份
        'vod_area' => 'China',  // 视频地区
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allLines) . " 个资源\n文件路径: " . $filePath . "\n【聚合模式】所有资源合并到一个项目中",  // 视频内容
        'vod_play_from' => '资源列表',  // 播放来源
        'vod_play_url' => $playUrlStr,  // 播放URL
        'is_aggregated' => true  // 聚合标志
    ];
    
    return $videoList;  // 返回视频列表
}

// 获取单资源模式的TXT视频
function getSingleTxtVideos($filePath, $fileName, $allLines, $defaultImages) {  // 获取单资源TXT视频
    $videoList = [];  // 初始化视频列表
    
    // 单资源模式：每个资源都显示为单独的视频
    foreach ($allLines as $index => $lineData) {  // 遍历所有行数据
        $imgIndex = $index % count($defaultImages);  // 计算图片索引
        
        $playSource = 'Online';  // 默认播放源
        if ($lineData['is_magnet']) {  // 如果是磁力链接
            $playSource = 'Magnet';  // 设置磁力播放源
        } elseif ($lineData['is_ed2k']) {  // 如果是电驴链接
            $playSource = 'Ed2k';  // 设置电驴播放源
        }
        
        $videoList[] = [  // 添加到视频列表
            'vod_id' => 'txt_single_' . md5($filePath) . '_' . $lineData['line_number'],  // 视频ID（文件路径MD5+行号）
            'vod_name' => $lineData['name'],  // 视频名称
            'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
            'vod_remarks' => $lineData['is_magnet'] ? 'Magnet' : ($lineData['is_ed2k'] ? 'Ed2k' : 'HD'),  // 视频备注
            'vod_year' => date('Y'),  // 视频年份
            'vod_area' => 'China',  // 视频地区
            'vod_content' => $lineData['name'] . ' - 来自TXT文件的资源',  // 视频内容
            'vod_play_from' => $playSource,  // 播放来源
            'vod_play_url' => 'Play$' . $lineData['link'],  // 播放URL
            'is_single' => true  // 单资源标志
        ];
    }
    
    return $videoList;  // 返回视频列表
}
// ==================== 第五部分：文件扫描和基础功能 ====================

function directPlayUrl($playUrl) {  // 直接播放URL处理
    $playUrl = urldecode($playUrl);  // URL解码播放URL
    
    // 如果是本地文件路径，转换为file://协议
    if (file_exists($playUrl) && strpos($playUrl, '://') === false) {  // 检查是否为本地文件
        $playUrl = 'file://' . $playUrl;  // 添加file://协议
    }
    
    $playType = 'video';  // 默认播放类型
    $needParse = 0;  // 不需要解析
    
    if (strpos($playUrl, 'magnet:') === 0) {  // 检查是否为磁力链接
        $playType = 'magnet';  // 设置磁力类型
    } elseif (strpos($playUrl, 'ed2k://') === 0) {  // 检查是否为电驴链接
        $playType = 'ed2k';  // 设置电驴类型
    } elseif (strpos($playUrl, '.m3u8') !== false) {  // 检查是否为HLS流
        $playType = 'hls';  // 设置HLS类型
    } elseif (strpos($playUrl, 'file://') === 0) {  // 检查是否为本地文件
        $playType = 'local';  // 设置本地类型
    }
    
    return [  // 返回播放信息
        'parse' => $needParse,  // 解析标志
        'playUrl' => '',  // 播放URL
        'url' => $playUrl,  // 实际URL
        'header' => [  // 请求头
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',  // 用户代理
            'Referer' => parse_url($playUrl, PHP_URL_SCHEME) . '://' . parse_url($playUrl, PHP_URL_HOST)  // 引用页
        ],
        'type' => $playType  // 播放类型
    ];
}

function scanDirectoryRecursive($directory, $fileTypes, $currentDepth = 1, $maxDepth = 50) {  // 递归扫描目录
    $fileList = [];  // 初始化文件列表
    
    if (!is_dir($directory) || $currentDepth > $maxDepth) {  // 检查目录是否存在或超过最大深度
        return $fileList;  // 返回空列表
    }
    
    try {  // 尝试扫描目录
        $items = @scandir($directory);  // 扫描目录
        if ($items === false) {  // 如果扫描失败
            return $fileList;  // 返回空列表
        }
        
        foreach ($items as $item) {  // 遍历目录项
            if ($item === '.' || $item === '..') continue;  // 跳过当前和上级目录
            
            $fullPath = rtrim($directory, '/') . '/' . $item;  // 构建完整路径
            
            if (is_dir($fullPath)) {  // 如果是目录
                $subFiles = scanDirectoryRecursive($fullPath . '/', $fileTypes, $currentDepth + 1, $maxDepth);  // 递归扫描子目录
                $fileList = array_merge($fileList, $subFiles);  // 合并文件列表
            } else {  // 如果是文件
                $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));  // 获取文件扩展名
                if (in_array($extension, $fileTypes)) {  // 检查扩展名是否在支持列表中
                    $relativePath = str_replace(BASE_SCAN_PATH, '', $fullPath);  // 计算相对路径
                    
                    $fileList[] = [  // 添加到文件列表
                        'type' => $extension,  // 文件类型
                        'path' => $fullPath,  // 完整路径
                        'name' => $item,  // 文件名
                        'filename' => pathinfo($item, PATHINFO_FILENAME),  // 文件名（不含扩展名）
                        'relative_path' => $relativePath,  // 相对路径
                        'depth' => $currentDepth  // 深度
                    ];
                }
            }
        }
    } catch (Exception $e) {  // 捕获异常
        return $fileList;  // 返回文件列表
    }
    
    return $fileList;  // 返回文件列表
}

function getAllFiles() {  // 获取所有文件
    static $allFiles = null;  // 静态变量缓存文件列表
    
    if ($allFiles === null) {  // 如果文件列表为空
        $allFiles = [];  // 初始化文件列表
        
        if (!is_dir(BASE_SCAN_PATH)) {  // 检查基础路径是否存在
            return $allFiles;  // 返回空列表
        }
        
        // 扫描所有支持的文件类型，包括.magnets
        $allFiles = scanDirectoryRecursive(BASE_SCAN_PATH, [  // 递归扫描目录
            'json', 'txt', 'magnets', 'm3u', 'm3u8', 'db', 'sqlite', 'sqlite3', 'db3'  // 支持的文件类型
        ]);
        
        usort($allFiles, function($a, $b) {  // 按相对路径排序
            return strcmp($a['relative_path'], $b['relative_path']);  // 比较相对路径
        });
    }
    
    return $allFiles;  // 返回文件列表
}

function detectFileType($filePath) {  // 检测文件类型
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return 'unknown';  // 返回未知类型
    }
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));  // 获取文件扩展名
    
    // .magnets 后缀直接识别为磁力文件
    if ($extension === 'magnets') {  // 检查是否为磁力文件
        return 'magnet_txt';  // 返回磁力文本类型
    }
    
    return $extension;  // 返回文件扩展名
}

// 新增：格式化文件大小
function formatFileSize($bytes) {  // 格式化文件大小
    if ($bytes == 0) return '0 B';  // 0字节返回0 B
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];  // 单位数组
    $i = floor(log($bytes, 1024));  // 计算单位索引
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];  // 返回格式化大小
}

// 新增：获取媒体文件缩略图
function getMediaThumbnail($file, $mediaType) {  // 获取媒体缩略图
    $defaultImages = [  // 默认图片数组
        'video' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频默认图片
        'audio' => 'https://www.252035.xyz/imgs?t=1335527662'  // 音频默认图片
    ];
    
    return $defaultImages[$mediaType] ?? 'https://www.252035.xyz/imgs?t=1335527662';  // 返回对应类型的图片
}

// 新增：获取媒体文件（视频和音频）
function getMediaFiles() {  // 获取媒体文件
    static $mediaFiles = null;  // 静态变量缓存媒体文件
    
    if ($mediaFiles === null) {  // 如果媒体文件为空
        global $MEDIA_EXTENSIONS;  // 引入全局变量
        $mediaFiles = [  // 初始化媒体文件数组
            'video' => [],  // 视频文件数组
            'audio' => [],  // 音频文件数组
            'aggregated' => []  // 聚合项目数组
        ];
        
        if (!is_dir(BASE_SCAN_PATH)) {  // 检查基础路径是否存在
            return $mediaFiles;  // 返回空数组
        }
        
        // 扫描视频文件
        $videoFiles = scanDirectoryRecursive(BASE_SCAN_PATH, $MEDIA_EXTENSIONS['video']);  // 扫描视频文件
        foreach ($videoFiles as $file) {  // 遍历视频文件
            $mediaFiles['video'][] = $file;  // 添加到视频数组
        }
        
        // 扫描音频文件
        $audioFiles = scanDirectoryRecursive(BASE_SCAN_PATH, $MEDIA_EXTENSIONS['audio']);  // 扫描音频文件
        foreach ($audioFiles as $file) {  // 遍历音频文件
            $mediaFiles['audio'][] = $file;  // 添加到音频数组
        }
        
        // 创建聚合项目
        if (!empty($videoFiles) || !empty($audioFiles)) {  // 如果有媒体文件
            $mediaFiles['aggregated'] = createMediaAggregatedProjects($videoFiles, $audioFiles);  // 创建聚合项目
        }
        
        // 按文件名排序
        usort($mediaFiles['video'], function($a, $b) {  // 视频文件排序
            return strcmp($a['filename'], $b['filename']);  // 比较文件名
        });
        
        usort($mediaFiles['audio'], function($a, $b) {  // 音频文件排序
            return strcmp($a['filename'], $b['filename']);  // 比较文件名
        });
    }
    
    return $mediaFiles;  // 返回媒体文件
}
// ==================== 第六部分：JSON和M3U文件解析 ====================

function parseJsonFile($filePath) {  // 解析JSON文件
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return [];  // 文件不存在返回空数组
    }
    
    $jsonContent = @file_get_contents($filePath);  // 读取文件内容
    if ($jsonContent === false) {  // 如果读取失败
        return [];  // 返回空数组
    }
    
    if (substr($jsonContent, 0, 3) == "\xEF\xBB\xBF") {  // 检查BOM头
        $jsonContent = substr($jsonContent, 3);  // 去除BOM头
    }
    
    $data = json_decode($jsonContent, true);  // 解析JSON
    if (!$data) {  // 如果解析失败
        return [];  // 返回空数组
    }
    
    if (!isset($data['list']) || !is_array($data['list'])) {  // 检查list字段
        return [];  // 返回空数组
    }
    
    return $data['list'];  // 返回列表数据
}

function parseM3uFile($filePath) {  // 解析M3U文件
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return [];  // 文件不存在返回空数组
    }
    
    $handle = @fopen($filePath, 'r');  // 打开文件
    if (!$handle) {  // 如果打开失败
        return [];  // 返回空数组
    }
    
    $videoList = [];  // 初始化视频列表
    $lineNumber = 0;  // 初始化行号计数器
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    // 读取所有频道
    $allChannels = [];  // 初始化所有频道数组
    $currentName = '';  // 初始化当前名称
    $currentIcon = '';  // 初始化当前图标
    $currentGroup = '';  // 初始化当前分组
    
    while (($line = fgets($handle)) !== false) {  // 逐行读取
        $lineNumber++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        if ($line === '') continue;  // 跳过空行
        
        if (strpos($line, '#EXTM3U') === 0) {  // 检查M3U头
            continue;  // 跳过
        }
        
        if (strpos($line, '#EXTINF:') === 0) {  // 检查频道信息行
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
            
            $parts = explode(',', $line, 2);  // 分割频道信息
            if (count($parts) > 1) {  // 如果有名称部分
                $currentName = trim($parts[1]);  // 设置当前名称
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {  // 匹配图标
                $currentIcon = trim($iconMatches[1]);  // 设置当前图标
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {  // 匹配分组
                $currentGroup = trim($groupMatches[1]);  // 设置当前分组
            }
            continue;  // 继续下一行
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];  // 有效协议数组
        $hasValidProtocol = false;  // 初始化有效协议标志
        foreach ($validProtocols as $protocol) {  // 遍历有效协议
            if (stripos($line, $protocol) === 0) {  // 检查协议
                $hasValidProtocol = true;  // 设置有效协议标志
                break;  // 跳出循环
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {  // 如果有有效协议和名称
            $allChannels[] = [  // 添加到频道数组
                'name' => $currentName,  // 频道名称
                'url' => $line,  // 频道URL
                'icon' => $currentIcon,  // 频道图标
                'group' => $currentGroup,  // 频道分组
                'line_number' => $lineNumber  // 行号
            ];
            
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    
    if (empty($allChannels)) {  // 如果没有频道
        return [];  // 返回空数组
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);  // 获取文件名
    
    // 根据显示模式决定返回内容
    switch (DISPLAY_MODE) {  // 根据显示模式选择
        case 'aggregated':  // 聚合模式
            // 聚合模式：只返回一个聚合项目
            return getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages);  // 返回聚合视频
            
        case 'single':  // 单资源模式
            // 单资源模式：返回所有单独的频道
            return getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages);  // 返回单资源视频
            
        case 'both':  // 两种模式
        default:  // 默认情况
            // 两种模式都显示：先显示聚合项目，再显示单个频道
            $aggregated = getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages);  // 获取聚合视频
            $single = getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages);  // 获取单资源视频
            return array_merge($aggregated, $single);  // 合并两种模式的结果
    }
}

// 获取聚合模式的M3U视频
function getAggregatedM3uVideo($filePath, $fileName, $allChannels, $defaultImages) {  // 获取聚合M3U视频
    $videoList = [];  // 初始化视频列表
    
    $imgIndex = 0 % count($defaultImages);  // 计算图片索引
    
    // 构建播放列表 - 使用频道名称作为线路名称
    $playUrls = [];  // 初始化播放URL数组
    foreach ($allChannels as $index => $channelData) {  // 遍历所有频道
        $playSource = $channelData['name'];  // 获取播放源名称
        // 如果有分组信息，添加到名称中
        if (!empty($channelData['group'])) {  // 如果有分组
            $playSource = $channelData['name'] . ' [' . $channelData['group'] . ']';  // 添加分组信息
        }
        
        $playUrls[] = $playSource . '$' . $channelData['url'];  // 添加到播放URL数组
    }
    
    $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
    
    // 创建单个视频项目，包含所有频道
    $videoList[] = [  // 添加到视频列表
        'vod_id' => 'm3u_aggregated_' . md5($filePath),  // 视频ID（文件路径MD5）
        'vod_name' => '[聚合] ' . $fileName,  // 视频名称
        'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
        'vod_remarks' => count($allChannels) . '个频道',  // 视频备注
        'vod_year' => date('Y'),  // 视频年份
        'vod_area' => 'China',  // 视频地区
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allChannels) . " 个电视频道\n文件路径: " . $filePath . "\n【聚合模式】所有频道合并到一个项目中",  // 视频内容
        'vod_play_from' => '频道列表',  // 播放来源
        'vod_play_url' => $playUrlStr,  // 播放URL
        'is_aggregated' => true  // 聚合标志
    ];
    
    return $videoList;  // 返回视频列表
}

// 获取单资源模式的M3U视频
function getSingleM3uVideos($filePath, $fileName, $allChannels, $defaultImages) {  // 获取单资源M3U视频
    $videoList = [];  // 初始化视频列表
    
    // 单资源模式：每个频道都显示为单独的视频
    foreach ($allChannels as $index => $channelData) {  // 遍历所有频道
        $imgIndex = $index % count($defaultImages);  // 计算图片索引
        
        $videoCover = $channelData['icon'];  // 获取视频封面
        if (empty($videoCover) || !filter_var($videoCover, FILTER_VALIDATE_URL)) {  // 检查封面是否有效
            $videoCover = $defaultImages[$imgIndex];  // 使用默认封面
        }
        
        $playSource = '直播';  // 默认播放源
        if (!empty($channelData['group'])) {  // 如果有分组
            $playSource = $channelData['group'];  // 使用分组作为播放源
        }
        
        if (strpos($channelData['url'], 'magnet:') === 0) {  // 检查是否为磁力链接
            $playSource = '磁力';  // 设置磁力播放源
        } elseif (strpos($channelData['url'], 'ed2k://') === 0) {  // 检查是否为电驴链接
            $playSource = '电驴';  // 设置电驴播放源
        }
        
        $videoList[] = [  // 添加到视频列表
            'vod_id' => 'm3u_single_' . md5($filePath) . '_' . $channelData['line_number'],  // 视频ID（文件路径MD5+行号）
            'vod_name' => $channelData['name'],  // 视频名称
            'vod_pic' => $videoCover,  // 视频图片
            'vod_remarks' => '直播',  // 视频备注
            'vod_year' => date('Y'),  // 视频年份
            'vod_area' => '中国大陆',  // 视频地区
            'vod_content' => $channelData['name'] . ' 直播频道',  // 视频内容
            'vod_play_from' => $playSource,  // 播放来源
            'vod_play_url' => 'Play$' . $channelData['url'],  // 播放URL
            'is_single' => true  // 单资源标志
        ];
    }
    
    return $videoList;  // 返回视频列表
}
// ==================== 第七部分：数据库解析功能 ====================

function parseDatabaseFile($filePath) {  // 解析数据库文件
    global $SUPPORTED_DB_TABLES, $DB_FIELD_MAPPING;  // 引入全局变量
    
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return [];  // 文件不存在返回空数组
    }
    
    if (!extension_loaded('pdo_sqlite')) {  // 检查PDO_SQLite扩展
        return [];  // 扩展未加载返回空数组
    }
    
    try {  // 尝试连接数据库
        $db = new PDO("sqlite:" . $filePath);  // 创建PDO连接
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // 设置错误模式
        
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
        
        if (empty($tables)) {  // 如果没有表
            return [];  // 返回空数组
        }
        
        $dbType = identifyDatabaseType($tables, $db);  // 识别数据库类型
        
        switch ($dbType) {  // 根据数据库类型选择解析方法
            case 'video_category':  // 视频分类数据库
                return parseVideoCategoryDatabase($db, $filePath);  // 解析视频分类数据库
            case 'magnet_database':  // 磁力数据库
                return parseMagnetDatabase($db, $filePath);  // 解析磁力数据库
            case 'live_channel':  // 直播频道数据库
                return parseLiveChannelDatabase($db, $filePath);  // 解析直播频道数据库
            case 'universal_video':  // 通用视频数据库
                return parseUniversalVideoDatabase($db, $filePath);  // 解析通用视频数据库
            case 'json_data_database':  // JSON数据数据库
                return parseJsonDataDatabase($db, $filePath);  // 解析JSON数据数据库
            default:  // 默认情况
                return parseAutoDetectDatabase($db, $filePath, $tables);  // 自动检测解析
        }
        
    } catch (PDOException $e) {  // 捕获数据库异常
        return [];  // 返回空数组
    }
}

// ==================== 第七部分：数据库类型识别函数（增强版） ====================

/**
 * 识别数据库类型 - 增强兼容性版本
 * 支持带分类表的数据库正常解析
 */
function identifyDatabaseType($tables, $db) {
    global $SUPPORTED_DB_TABLES;
    
    // 第一步：优先检查JSON数据数据库（包含磁力链接的数据库）
    foreach ($tables as $table) {
        // 跳过SQLite系统表
        if (strpos($table, 'sqlite_') === 0) continue;
        
        try {
            // 获取表的字段信息
            $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            $fieldNames = array_column($fieldInfo, 'name');
            
            // 检查是否有data或json_data字段
            if (in_array('data', $fieldNames) || in_array('json_data', $fieldNames)) {
                // 获取样本数据检查是否包含磁力链接
                $sampleData = $db->query("SELECT data FROM $table LIMIT 1")->fetch(PDO::FETCH_COLUMN);
                if ($sampleData && (strpos($sampleData, '"magnet":') !== false || strpos($sampleData, 'magnet:?xt=') !== false)) {
                    // 识别为JSON数据数据库（包含磁力链接）
                    return 'json_data_database';
                }
            }
        } catch (Exception $e) {
            // 如果检查过程出错，继续检查下一个表
            continue;
        }
    }
    
    // 第二步：检查磁力表（专门存储磁力链接的表）
    foreach ($tables as $table) {
        // 使用正则表达式匹配磁力相关的表名
        if (preg_match($SUPPORTED_DB_TABLES['magnet'], $table)) {
            return 'magnet_database';
        }
    }
    
    // 第三步：检查直播频道表
    foreach ($tables as $table) {
        // 使用正则表达式匹配频道相关的表名
        if (preg_match($SUPPORTED_DB_TABLES['channel'], $table)) {
            return 'live_channel';
        }
    }
    
    // 第四步：检查通用视频表
    foreach ($tables as $table) {
        // 使用正则表达式匹配视频相关的表名
        if (preg_match($SUPPORTED_DB_TABLES['video'], $table)) {
            return 'universal_video';
        }
    }
    
    // 第五步：最后检查视频分类数据库（确保兼容性）
    if (in_array('videos', $tables) && in_array('categories', $tables)) {
        // 进一步验证表结构，确保videos表有必要的播放字段
        try {
            // 获取videos表的所有字段名
            $videoFields = $db->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_COLUMN, 1);
            // 检查videos表是否有播放相关的字段
            $hasPlayField = in_array('play_url', $videoFields) || 
                           in_array('url', $videoFields) || 
                           in_array('link', $videoFields) || 
                           in_array('magnet', $videoFields);
            if ($hasPlayField) {
                // 确认是有效的视频分类数据库
                return 'video_category';
            }
        } catch (Exception $e) {
            // 如果字段检查失败，仍然尝试解析（兼容性处理）
            return 'video_category';
        }
    }
    
    // 第六步：如果以上都不匹配，使用自动检测
    return 'auto_detect';
}
// 新增：解析JSON数据数据库（专门处理包含磁力链接的数据库）
function parseJsonDataDatabase($db, $filePath) {  // 解析JSON数据数据库
    $videoList = [];  // 初始化视频列表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    foreach ($tables as $table) {  // 遍历所有表
        if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $jsonField = null;  // 初始化JSON字段
        foreach ($fieldNames as $field) {  // 遍历字段名
            $lowerField = strtolower($field);  // 转换为小写
            if (in_array($lowerField, ['data', 'json_data', 'info', 'content', 'json'])) {  // 检查是否为JSON字段
                $jsonField = $field;  // 设置JSON字段
                break;  // 跳出循环
            }
        }
        
        if (!$jsonField) {  // 如果没有JSON字段
            continue;  // 继续下一个表
        }
        
        // 获取所有包含JSON数据的记录
        try {  // 尝试查询数据
            $results = $db->query("SELECT $jsonField FROM $table LIMIT " . MAX_DB_RESULTS)->fetchAll(PDO::FETCH_COLUMN);  // 查询JSON数据
            
            foreach ($results as $index => $jsonData) {  // 遍历JSON数据
                if (empty($jsonData)) continue;  // 跳过空数据
                
                $videoData = json_decode($jsonData, true);  // 解析JSON数据
                if (!$videoData || !is_array($videoData)) continue;  // 跳过无效数据
                
                // 从JSON数据中提取视频信息
                $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';  // 提取视频名称
                $videoLink = '';  // 初始化视频链接
                $playSource = 'Database';  // 初始化播放源
                
                // 优先使用磁力链接
                if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {  // 检查磁力链接
                    $videoLink = $videoData['magnet'];  // 设置视频链接
                    $playSource = 'Magnet';  // 设置播放源
                } 
                // 其次使用torrent链接
                elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {  // 检查种子链接
                    $videoLink = $videoData['torrent'];  // 设置视频链接
                    $playSource = 'Torrent';  // 设置播放源
                }
                // 最后使用普通链接
                elseif (isset($videoData['link']) && !empty($videoData['link'])) {  // 检查普通链接
                    $videoLink = $videoData['link'];  // 设置视频链接
                    if (strpos($videoLink, 'magnet:') === 0) {  // 检查是否为磁力链接
                        $playSource = 'Magnet';  // 设置播放源
                    } elseif (strpos($videoLink, 'ed2k://') === 0) {  // 检查是否为电驴链接
                        $playSource = 'Ed2k';  // 设置播放源
                    }
                }
                
                if (empty($videoLink)) {  // 如果没有视频链接
                    continue;  // 跳过当前数据
                }
                
                // 提取其他信息
                $videoCover = $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[$index % count($defaultImages)];  // 提取视频封面
                $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content';  // 提取视频描述
                $videoYear = $videoData['year'] ?? '';  // 提取视频年份
                $videoArea = $videoData['area'] ?? $videoData['region'] ?? 'International';  // 提取视频地区
                $videoSize = $videoData['size'] ?? '';  // 提取视频大小
                $uploader = $videoData['uploader'] ?? '';  // 提取上传者
                
                // 构建内容描述
                $content = $videoDesc;  // 初始化内容
                if (!empty($uploader)) {  // 如果有上传者
                    $content .= "\n上传者: " . $uploader;  // 添加上传者信息
                }
                if (!empty($videoSize)) {  // 如果有视频大小
                    $content .= "\n大小: " . $videoSize;  // 添加大小信息
                }
                if (isset($videoData['imdb']) && !empty($videoData['imdb'])) {  // 如果有IMDb信息
                    $content .= "\nIMDb: " . $videoData['imdb'];  // 添加IMDb信息
                }
                
                // 修复：正确传递磁力链接给播放器
                $videoList[] = [  // 添加到视频列表
                    'vod_id' => 'json_db_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                    'vod_name' => $videoName,  // 视频名称
                    'vod_pic' => $videoCover,  // 视频封面
                    'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',  // 视频备注
                    'vod_year' => $videoYear,  // 视频年份
                    'vod_area' => $videoArea,  // 视频地区
                    'vod_content' => $content,  // 视频内容
                    'vod_play_from' => $playSource,  // 播放来源
                    'vod_play_url' => 'Play$' . $videoLink  // 播放URL
                ];
                
                if (count($videoList) >= MAX_DB_RESULTS) {  // 如果达到最大结果数
                    break 2;  // 跳出两层循环
                }
            }
        } catch (Exception $e) {  // 捕获异常
            continue;  // 继续下一个表
        }
    }
    
    $db = null;  // 关闭数据库连接
    return $videoList;  // 返回视频列表
}
// ==================== 第八部分：数据库解析续和分类管理 ====================

function parseMagnetDatabase($db, $filePath) {  // 解析磁力数据库
    $videoList = [];  // 初始化视频列表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    foreach ($tables as $table) {  // 遍历所有表
        if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        // 检查是否有data字段（包含JSON数据）
        if (in_array('data', $fieldNames)) {  // 检查是否有data字段
            $results = $db->query("SELECT data FROM $table LIMIT " . MAX_DB_RESULTS)->fetchAll(PDO::FETCH_COLUMN);  // 查询数据
            
            foreach ($results as $index => $jsonData) {  // 遍历数据
                if (empty($jsonData)) continue;  // 跳过空数据
                
                $videoData = json_decode($jsonData, true);  // 解析JSON数据
                if ($videoData && is_array($videoData)) {  // 检查数据有效性
                    $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';  // 提取视频名称
                    $videoLink = '';  // 初始化视频链接
                    $playSource = 'Database';  // 初始化播放源
                    
                    if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {  // 检查磁力链接
                        $videoLink = $videoData['magnet'];  // 设置视频链接
                        $playSource = 'Magnet';  // 设置播放源
                    } elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {  // 检查种子链接
                        $videoLink = $videoData['torrent'];  // 设置视频链接
                        $playSource = 'Torrent';  // 设置播放源
                    }
                    
                    if (empty($videoLink)) continue;  // 跳过没有链接的数据
                    
                    // 提取其他信息
                    $videoCover = $videoData['image'] ?? $defaultImages[$index % count($defaultImages)];  // 提取视频封面
                    $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoName . ' content';  // 提取视频描述
                    $videoYear = $videoData['year'] ?? date('Y');  // 提取视频年份
                    $videoArea = $videoData['area'] ?? 'International';  // 提取视频地区
                    $videoSize = $videoData['size'] ?? '';  // 提取视频大小
                    $uploader = $videoData['uploader'] ?? '';  // 提取上传者
                    
                    // 构建内容描述
                    $content = $videoDesc;  // 初始化内容
                    if (!empty($uploader)) {  // 如果有上传者
                        $content .= "\n上传者: " . $uploader;  // 添加上传者信息
                    }
                    if (!empty($videoSize)) {  // 如果有视频大小
                        $content .= "\n大小: " . $videoSize;  // 添加大小信息
                    }
                    
                    // 修复：正确传递磁力链接给播放器
                    $videoList[] = [  // 添加到视频列表
                        'vod_id' => 'db_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                        'vod_name' => $videoName,  // 视频名称
                        'vod_pic' => $videoCover,  // 视频封面
                        'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',  // 视频备注
                        'vod_year' => $videoYear,  // 视频年份
                        'vod_area' => $videoArea,  // 视频地区
                        'vod_content' => $content,  // 视频内容
                        'vod_play_from' => $playSource,  // 播放来源
                        'vod_play_url' => 'Play$' . $videoLink  // 播放URL
                    ];
                    
                    if (count($videoList) >= MAX_DB_RESULTS) break 2;  // 达到最大结果数时跳出
                }
            }
        }
    }
    
    $db = null;  // 关闭数据库连接
    return $videoList;  // 返回视频列表
}
/**
 * 解析视频分类数据库 - 增强兼容性版本
 * 支持不同字段名的数据库结构，同时保留分类信息
 */
function parseVideoCategoryDatabase($db, $filePath) {
    $videoList = []; // 初始化视频列表
    
    // 第一步：构建分类映射表（用于将分类ID转换为分类名称）
    $categoryMap = [];
    try {
        // 查询分类表中的所有分类
        $categories = $db->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        // 将分类数据转换为ID=>名称的映射数组
        foreach ($categories as $cat) {
            $categoryMap[$cat['id']] = $cat['name'];
        }
    } catch (Exception $e) {
        // 如果分类表查询失败，继续处理视频（不影响视频解析）
        $categoryMap = [];
    }
    
    // 第二步：动态检测videos表的实际字段
    $videoFields = [];
    try {
        // 获取videos表的结构信息
        $fieldInfo = $db->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_ASSOC);
        // 提取所有字段名
        $videoFields = array_column($fieldInfo, 'name');
    } catch (Exception $e) {
        // 如果无法获取字段信息，使用默认字段列表作为备选
        $videoFields = ['id', 'name', 'category_id', 'image', 'actor', 'director', 'remarks', 'pubdate', 'area', 'year', 'content', 'play_url'];
    }
    
    // 第三步：构建查询字段列表（只查询实际存在的字段）
    $selectFields = [];
    // 定义所有可能的字段名（兼容不同数据库设计）
    $possibleFields = [
        'id', 'category_id', 
        'name', 'title', // 视频名称字段
        'image', 'pic', 'cover', // 封面图片字段
        'actor', 'director', // 演员导演字段
        'remarks', 'pubdate', // 备注和发布日期字段
        'area', 'region', // 地区字段
        'year', // 年份字段
        'content', 'desc', 'description', // 内容描述字段
        'play_url', 'url', 'link', 'magnet' // 播放链接字段
    ];
    
    // 遍历所有可能的字段，只选择实际存在的字段
    foreach ($possibleFields as $field) {
        if (in_array($field, $videoFields)) {
            $selectFields[] = $field;
        }
    }
    
    // 第四步：确保查询字段列表不为空（最低要求）
    if (empty($selectFields)) {
        // 如果没有任何匹配字段，使用最基本的字段
        $selectFields = ['id', 'name'];
    }
    
    // 第五步：构建SQL查询语句
    $querySQL = "SELECT " . implode(', ', $selectFields) . " FROM videos LIMIT " . MAX_DB_RESULTS;
    
    try {
        // 执行查询获取视频数据
        $videos = $db->query($querySQL)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // 如果查询失败，返回空数组
        return [];
    }
    
    // 第六步：设置默认图片（用于没有封面的视频）
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];
    
    // 第七步：遍历处理每个视频记录
    foreach ($videos as $index => $video) {
        // 7.1 提取视频名称（支持多个字段名）
        $videoName = $video['name'] ?? $video['title'] ?? 'Unknown Video';
        
        // 7.2 提取播放URL（支持多个字段名，按优先级尝试）
        $playUrl = '';
        $urlFields = ['play_url', 'url', 'link', 'magnet']; // 字段优先级顺序
        foreach ($urlFields as $field) {
            if (!empty($video[$field])) {
                $playUrl = $video[$field];
                break; // 找到第一个有效的播放链接就停止
            }
        }
        
        // 跳过没有播放URL的视频（无法播放）
        if (empty($playUrl)) {
            continue;
        }
        
        // 7.3 确定播放源类型（根据URL协议判断）
        $playSource = 'Video'; // 默认类型
        if (strpos($playUrl, 'magnet:') === 0) {
            $playSource = 'Magnet'; // 磁力链接
        } elseif (strpos($playUrl, 'ed2k://') === 0) {
            $playSource = 'Ed2k'; // 电驴链接
        }
        
        // 7.4 获取分类名称（如果存在分类信息）
        $categoryId = $video['category_id'] ?? null;
        $categoryName = '';
        if ($categoryId && isset($categoryMap[$categoryId])) {
            $categoryName = $categoryMap[$categoryId];
        }
        
        // 7.5 构建播放来源显示（包含分类信息）
        $playFrom = $playSource;
        if (!empty($categoryName)) {
            // 如果有关联分类，显示为"播放源 · 分类名"
            $playFrom = $playSource . ' · ' . $categoryName;
        }
        
        // 7.6 提取封面图片（支持多个字段名）
        $videoCover = $defaultImages[$index % count($defaultImages)]; // 默认图片
        $coverFields = ['image', 'pic', 'cover']; // 封面字段优先级
        foreach ($coverFields as $field) {
            if (!empty($video[$field])) {
                $videoCover = $video[$field];
                break; // 找到第一个有效的封面就停止
            }
        }
        
        // 7.7 提取内容描述（支持多个字段名）
        $videoContent = $videoName . ' content'; // 默认描述
        $contentFields = ['content', 'desc', 'description']; // 内容字段优先级
        foreach ($contentFields as $field) {
            if (!empty($video[$field])) {
                $videoContent = $video[$field];
                break; // 找到第一个有效的内容就停止
            }
        }
        
        // 7.8 构建最终的视频信息数组
        $videoList[] = [
            'vod_id' => 'video_' . ($video['id'] ?? $index), // 视频ID（优先使用数据库ID）
            'vod_name' => $videoName, // 视频名称
            'vod_pic' => $videoCover, // 视频封面
            'vod_remarks' => $video['remarks'] ?? 'HD', // 视频备注
            'vod_year' => $video['year'] ?? '', // 发布年份
            'vod_area' => $video['area'] ?? $video['region'] ?? 'China', // 地区信息
            'vod_actor' => $video['actor'] ?? '', // 演员信息
            'vod_director' => $video['director'] ?? '', // 导演信息
            'vod_content' => $videoContent, // 内容描述
            'vod_play_from' => $playFrom, // 播放来源（包含分类）
            'vod_play_url' => 'Play$' . $playUrl // 播放URL（格式化）
        ];
        
        // 7.9 检查是否达到最大结果限制
        if (count($videoList) >= MAX_DB_RESULTS) break;
    }
    
    // 第八步：返回解析后的视频列表
    return $videoList;
}

function parseUniversalVideoDatabase($db, $filePath) {  // 解析通用视频数据库
    global $DB_FIELD_MAPPING;  // 引入全局变量
    
    $videoList = [];  // 初始化视频列表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    foreach ($tables as $table) {  // 遍历所有表
        if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $mappedFields = [];  // 初始化映射字段
        foreach ($DB_FIELD_MAPPING as $stdField => $possibleFields) {  // 遍历字段映射
            foreach ($possibleFields as $candidate) {  // 遍历可能字段
                if (in_array($candidate, $fieldNames)) {  // 检查字段是否存在
                    $mappedFields[$stdField] = $candidate;  // 设置映射字段
                    break;  // 跳出内层循环
                }
            }
        }
        
        if (empty($mappedFields['name']) || (empty($mappedFields['url']) && empty($mappedFields['magnet']))) {  // 检查必要字段
            continue;  // 跳过不满足条件的表
        }
        
        $selectFields = array_values($mappedFields);  // 获取选择字段
        $querySQL = "SELECT " . implode(', ', $selectFields) . " FROM $table LIMIT " . MAX_DB_RESULTS;  // 构建查询SQL
        
        try {  // 尝试查询
            $stmt = $db->query($querySQL);  // 执行查询
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);  // 获取结果
            
            foreach ($results as $index => $row) {  // 遍历结果
                $videoName = $row[$mappedFields['name']] ?? 'Unknown Video';  // 提取视频名称
                $videoLink = '';  // 初始化视频链接
                $playSource = 'Database';  // 初始化播放源
                
                if (!empty($mappedFields['magnet']) && !empty($row[$mappedFields['magnet']])) {  // 检查磁力链接
                    $videoLink = $row[$mappedFields['magnet']];  // 设置视频链接
                    $playSource = 'Magnet';  // 设置播放源
                } elseif (!empty($mappedFields['url']) && !empty($row[$mappedFields['url']])) {  // 检查普通链接
                    $videoLink = $row[$mappedFields['url']];  // 设置视频链接
                    if (strpos($videoLink, 'magnet:') === 0) {  // 检查是否为磁力链接
                        $playSource = 'Magnet';  // 设置播放源
                    } elseif (strpos($videoLink, 'ed2k://') === 0) {  // 检查是否为电驴链接
                        $playSource = 'Ed2k';  // 设置播放源
                    } elseif (strpos($videoLink, 'http') === 0) {  // 检查是否为HTTP链接
                        $playSource = 'Online';  // 设置播放源
                    }
                }
                
                if (empty($videoLink)) {  // 如果没有视频链接
                    continue;  // 跳过当前数据
                }
                
                $videoCover = '';  // 初始化视频封面
                if (!empty($mappedFields['image']) && !empty($row[$mappedFields['image']])) {  // 检查封面字段
                    $videoCover = $row[$mappedFields['image']];  // 设置视频封面
                } else {  // 没有封面
                    $videoCover = $defaultImages[$index % count($defaultImages)];  // 使用默认封面
                }
                
                $videoInfo = [  // 构建视频信息
                    'vod_id' => 'db_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                    'vod_name' => $videoName,  // 视频名称
                    'vod_pic' => $videoCover,  // 视频封面
                    'vod_remarks' => 'HD',  // 视频备注
                    'vod_year' => !empty($mappedFields['year']) ? ($row[$mappedFields['year']] ?? date('Y')) : date('Y'),  // 视频年份
                    'vod_area' => !empty($mappedFields['area']) ? ($row[$mappedFields['area']] ?? 'China') : 'China',  // 视频地区
                    'vod_content' => !empty($mappedFields['content']) ? ($row[$mappedFields['content']] ?? $videoName . ' content') : $videoName . ' content',  // 视频内容
                    'vod_play_from' => $playSource,  // 播放来源
                    'vod_play_url' => 'Play$' . $videoLink  // 播放URL
                ];
                
                if (!empty($mappedFields['actor']) && !empty($row[$mappedFields['actor']])) {  // 检查演员字段
                    $videoInfo['vod_actor'] = $row[$mappedFields['actor']];  // 设置演员
                }
                
                if (!empty($mappedFields['director']) && !empty($row[$mappedFields['director']])) {  // 检查导演字段
                    $videoInfo['vod_director'] = $row[$mappedFields['director']];  // 设置导演
                }
                
                $videoList[] = $videoInfo;  // 添加到视频列表
                
                if (count($videoList) >= MAX_DB_RESULTS) {  // 达到最大结果数
                    break 2;  // 跳出两层循环
                }
            }
        } catch (Exception $e) {  // 捕获异常
            continue;  // 继续下一个表
        }
    }
    
    $db = null;  // 关闭数据库连接
    return $videoList;  // 返回视频列表
}

function parseLiveChannelDatabase($db, $filePath, $resourceName = 'Live Source') {  // 解析直播频道数据库
    $videoList = [];  // 初始化视频列表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    foreach ($tables as $table) {  // 遍历所有表
        if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
        
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $nameField = null;  // 初始化名称字段
        $urlField = null;  // 初始化URL字段
        $groupField = null;  // 初始化分组字段
        $iconField = null;  // 初始化图标字段
        
        foreach ($fieldNames as $field) {  // 遍历字段名
            $lowerField = strtolower($field);  // 转换为小写
            if (in_array($lowerField, ['name', 'title', 'channel_name', 'channel_title'])) {  // 检查名称字段
                $nameField = $field;  // 设置名称字段
            } elseif (in_array($lowerField, ['url', 'link', 'channel_url', 'play_url'])) {  // 检查URL字段
                $urlField = $field;  // 设置URL字段
            } elseif (in_array($lowerField, ['group', 'category', 'type'])) {  // 检查分组字段
                $groupField = $field;  // 设置分组字段
            } elseif (in_array($lowerField, ['logo', 'icon', 'image'])) {  // 检查图标字段
                $iconField = $field;  // 设置图标字段
            }
        }
        
        if (!$nameField || !$urlField) {  // 检查必要字段
            continue;  // 跳过不满足条件的表
        }
        
        $selectFields = [$nameField, $urlField];  // 初始化选择字段
        if ($groupField) $selectFields[] = $groupField;  // 添加分组字段
        if ($iconField) $selectFields[] = $iconField;  // 添加图标字段
        
        $querySQL = "SELECT " . implode(', ', $selectFields) . " FROM $table LIMIT 1000";  // 构建查询SQL
        
        try {  // 尝试查询
            $stmt = $db->query($querySQL);  // 执行查询
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);  // 获取结果
            
            foreach ($results as $index => $row) {  // 遍历结果
                $channelName = $row[$nameField] ?? 'Unknown Channel';  // 提取频道名称
                $channelUrl = $row[$urlField] ?? '';  // 提取频道URL
                $channelGroup = $groupField ? ($row[$groupField] ?? 'Live Channel') : 'Live Channel';  // 提取频道分组
                $channelIcon = $iconField ? ($row[$iconField] ?? '') : '';  // 提取频道图标
                
                if (empty($channelUrl)) {  // 如果没有频道URL
                    continue;  // 跳过当前数据
                }
                
                $videoCover = $channelIcon ?: $defaultImages[$index % count($defaultImages)];  // 设置视频封面
                
                $videoList[] = [  // 添加到视频列表
                    'vod_id' => 'live_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                    'vod_name' => $channelName,  // 视频名称
                    'vod_pic' => $videoCover,  // 视频封面
                    'vod_remarks' => 'Live',  // 视频备注
                    'vod_year' => date('Y'),  // 视频年份
                    'vod_area' => 'China',  // 视频地区
                    'vod_content' => $channelName . ' live channel',  // 视频内容
                    'vod_play_from' => $resourceName,  // 播放来源
                    'vod_play_url' => $resourceName . '$' . $channelUrl  // 播放URL
                ];
                
                if (count($videoList) >= 1000) {  // 达到最大结果数
                    break 2;  // 跳出两层循环
                }
            }
        } catch (Exception $e) {  // 捕获异常
            continue;  // 继续下一个表
        }
    }
    
    $db = null;  // 关闭数据库连接
    return $videoList;  // 返回视频列表
}

function parseAutoDetectDatabase($db, $filePath, $tables) {  // 自动检测解析数据库
    $videoList = [];  // 初始化视频列表
    
    foreach ($tables as $table) {  // 遍历所有表
        if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
        
        $videoList = array_merge($videoList, tryParseGenericTable($db, $filePath, $table));  // 尝试通用解析
        $videoList = array_merge($videoList, tryParseJsonTable($db, $filePath, $table));  // 尝试JSON解析
        
        if (count($videoList) >= MAX_DB_RESULTS) {  // 达到最大结果数
            break;  // 跳出循环
        }
    }
    
    $db = null;  // 关闭数据库连接
    return $videoList;  // 返回视频列表
}

function tryParseGenericTable($db, $filePath, $table) {  // 尝试通用表解析
    $videoList = [];  // 初始化视频列表
    
    try {  // 尝试解析
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $possibleNameFields = [];  // 初始化可能名称字段
        $possibleUrlFields = [];  // 初始化可能URL字段
        
        foreach ($fieldNames as $field) {  // 遍历字段名
            $lowerField = strtolower($field);  // 转换为小写
            if (strpos($lowerField, 'name') !== false || strpos($lowerField, 'title') !== false) {  // 检查名称字段
                $possibleNameFields[] = $field;  // 添加到可能名称字段
            }
            if (strpos($lowerField, 'url') !== false || strpos($lowerField, 'link') !== false || 
                strpos($lowerField, 'magnet') !== false) {  // 检查URL字段
                $possibleUrlFields[] = $field;  // 添加到可能URL字段
            }
        }
        
        if (empty($possibleNameFields) || empty($possibleUrlFields)) {  // 检查必要字段
            return $videoList;  // 返回空列表
        }
        
        $nameField = $possibleNameFields[0];  // 获取名称字段
        $urlField = $possibleUrlFields[0];  // 获取URL字段
        
        $querySQL = "SELECT $nameField, $urlField FROM $table LIMIT 500";  // 构建查询SQL
        $stmt = $db->query($querySQL);  // 执行查询
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);  // 获取结果
        
        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
        
        foreach ($results as $index => $row) {  // 遍历结果
            $videoName = $row[$nameField] ?? 'Unknown Video';  // 提取视频名称
            $videoUrl = $row[$urlField] ?? '';  // 提取视频URL
            
            if (empty($videoUrl)) {  // 如果没有视频URL
                continue;  // 跳过当前数据
            }
            
            $playSource = 'Database';  // 初始化播放源
            if (strpos($videoUrl, 'magnet:') === 0) {  // 检查是否为磁力链接
                $playSource = 'Magnet';  // 设置播放源
            } elseif (strpos($videoUrl, 'ed2k://') === 0) {  // 检查是否为电驴链接
                $playSource = 'Ed2k';  // 设置播放源
            }
            
            $videoList[] = [  // 添加到视频列表
                'vod_id' => 'auto_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                'vod_name' => $videoName,  // 视频名称
                'vod_pic' => $defaultImages[$index % count($defaultImages)],  // 视频封面
                'vod_remarks' => 'HD',  // 视频备注
                'vod_year' => date('Y'),  // 视频年份
                'vod_area' => 'China',  // 视频地区
                'vod_content' => $videoName . ' content',  // 视频内容
                'vod_play_from' => $playSource,  // 播放来源
                'vod_play_url' => 'Play$' . $videoUrl  // 播放URL
            ];
        }
    } catch (Exception $e) {  // 捕获异常
        // 静默处理异常
    }
    
    return $videoList;  // 返回视频列表
}

function tryParseJsonTable($db, $filePath, $table) {  // 尝试JSON表解析
    $videoList = [];  // 初始化视频列表
    
    try {  // 尝试解析
        $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $jsonField = null;  // 初始化JSON字段
        foreach ($fieldNames as $field) {  // 遍历字段名
            $lowerField = strtolower($field);  // 转换为小写
            if (in_array($lowerField, ['json', 'data', 'info', 'content'])) {  // 检查JSON字段
                $jsonField = $field;  // 设置JSON字段
                break;  // 跳出循环
            }
        }
        
        if (!$jsonField) {  // 如果没有JSON字段
            return $videoList;  // 返回空列表
        }
        
        $querySQL = "SELECT $jsonField FROM $table LIMIT 300";  // 构建查询SQL
        $stmt = $db->query($querySQL);  // 执行查询
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);  // 获取结果
        
        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
        
        foreach ($results as $index => $jsonData) {  // 遍历结果
            if (empty($jsonData)) continue;  // 跳过空数据
            
            $videoData = json_decode($jsonData, true);  // 解析JSON数据
            if (!$videoData || !is_array($videoData)) continue;  // 跳过无效数据
            
            $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';  // 提取视频名称
            $videoUrl = $videoData['url'] ?? $videoData['magnet'] ?? $videoData['link'] ?? '';  // 提取视频URL
            
            if (empty($videoUrl)) continue;  // 跳过没有URL的数据
            
            $playSource = 'Database';  // 初始化播放源
            if (strpos($videoUrl, 'magnet:') === 0) {  // 检查是否为磁力链接
                $playSource = 'Magnet';  // 设置播放源
            } elseif (strpos($videoUrl, 'ed2k://') === 0) {  // 检查是否为电驴链接
                $playSource = 'Ed2k';  // 设置播放源
            }
            
            $videoList[] = [  // 添加到视频列表
                'vod_id' => 'json_' . md5($filePath) . '_' . $table . '_' . $index,  // 视频ID
                'vod_name' => $videoName,  // 视频名称
                'vod_pic' => $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[$index % count($defaultImages)],  // 视频封面
                'vod_remarks' => 'HD',  // 视频备注
                'vod_year' => $videoData['year'] ?? date('Y'),  // 视频年份
                'vod_area' => $videoData['area'] ?? 'China',  // 视频地区
                'vod_content' => $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content',  // 视频内容
                'vod_play_from' => $playSource,  // 播放来源
                'vod_play_url' => 'Play$' . $videoUrl  // 播放URL
            ];
            
            if (count($videoList) >= 300) {  // 达到最大结果数
                break;  // 跳出循环
            }
        }
    } catch (Exception $e) {  // 捕获异常
        // 静默处理异常
    }
    
    return $videoList;  // 返回视频列表
}
// ==================== 第九部分：重构分类系统 - 按类型聚合子分类 ====================

// 重构获取分类列表函数 - 按文件类型聚合
function getCategoryList() {  // 获取分类列表
    static $categoryList = null;  // 静态变量缓存分类列表
    
    if ($categoryList === null) {  // 如果分类列表为空
        $allFiles = getAllFiles();  // 获取所有文件
        $mediaFiles = getMediaFiles();  // 获取媒体文件
        $categoryList = [];  // 初始化分类列表
        
        // 计算各类文件数量
        $fileTypeCounts = [  // 文件类型计数数组
            'json' => 0,  // JSON文件计数
            'txt' => 0,   // TXT文件计数
            'magnets' => 0,  // 磁力文件计数
            'm3u' => 0,  // M3U文件计数
            'm3u8' => 0,  // M3U8文件计数
            'db' => 0,  // 数据库文件计数
            'sqlite' => 0,  // SQLite文件计数
            'sqlite3' => 0,  // SQLite3文件计数
            'db3' => 0  // DB3文件计数
        ];
        
        foreach ($allFiles as $file) {  // 遍历所有文件
            $fileType = $file['type'];  // 获取文件类型
            if (isset($fileTypeCounts[$fileType])) {  // 检查类型是否在计数数组中
                $fileTypeCounts[$fileType]++;  // 增加计数
            }
        }
        
        $totalFiles = count($allFiles);  // 计算总文件数
        $totalMedia = count($mediaFiles['video']) + count($mediaFiles['audio']);  // 计算总媒体文件数
        
        // 只保留本地资源管理器作为唯一分类，包含媒体库
        $totalItems = $totalFiles + ($totalMedia > 0 ? 1 : 0);  // 计算总项目数
        $categoryList[] = [  // 添加聚合分类
            'type_id' => 'aggregated',  // 分类ID
            'type_name' => '本地资源管理器 (' . $totalItems . '个项目)',  // 分类名称
            'type_file' => 'aggregated',  // 分类文件
            'source_path' => 'aggregated',  // 源路径
            'source_type' => 'aggregated',  // 源类型
            'is_aggregated' => true  // 聚合标志
        ];
        
        if (empty($allFiles) && $totalMedia === 0) {  // 如果没有文件和媒体
            $categoryList[] = [  // 添加空分类
                'type_id' => '1',  // 分类ID
                'type_name' => 'No media files found',  // 分类名称
                'type_file' => 'empty',  // 分类文件
                'source_path' => 'empty',  // 源路径
                'source_type' => 'empty'  // 源类型
            ];
        }
    }
    
    return $categoryList;  // 返回分类列表
}

// 重构获取分类内容函数 - 支持三级分类
function getCategory($categoryId, $page) {  // 获取分类内容
    $categoryList = getCategoryList();  // 获取分类列表
    
    if (empty($categoryList)) {  // 如果没有分类
        return [];  // 返回空数组
    }
    
    // 处理主聚合分类 - 显示类型聚合子分类
    if ($categoryId === 'aggregated') {  // 检查是否为聚合分类
        $allFiles = getAllFiles();  // 获取所有文件
        $mediaFiles = getMediaFiles();  // 获取媒体文件
        $subCategories = [];  // 初始化子分类数组
        
        // 先添加媒体库（如果有媒体文件）
        if (!empty($mediaFiles['video']) || !empty($mediaFiles['audio'])) {  // 检查是否有媒体文件
            $totalMedia = count($mediaFiles['video']) + count($mediaFiles['audio']);  // 计算总媒体文件数
            $subCategories[] = [  // 添加媒体库子分类
                'vod_id' => 'aggregated_media_library',  // 视频ID
                'vod_name' => '媒体库 (' . $totalMedia . '个媒体文件)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '媒体',  // 视频备注
                'is_aggregated_sub' => true,  // 聚合子分类标志
                'is_media_library' => true  // 媒体库标志
            ];
        }
        
        // 按文件类型创建聚合子分类
        $fileTypeCounts = [  // 文件类型计数数组
            'json' => 0,  // JSON文件计数
            'txt' => 0,   // TXT文件计数
            'magnets' => 0,  // 磁力文件计数
            'm3u' => 0,  // M3U文件计数
            'm3u8' => 0,  // M3U8文件计数
            'db' => 0,  // 数据库文件计数
            'sqlite' => 0,  // SQLite文件计数
            'sqlite3' => 0,  // SQLite3文件计数
            'db3' => 0  // DB3文件计数
        ];
        
        foreach ($allFiles as $file) {  // 遍历所有文件
            $fileType = $file['type'];  // 获取文件类型
            if (isset($fileTypeCounts[$fileType])) {  // 检查类型是否在计数数组中
                $fileTypeCounts[$fileType]++;  // 增加计数
            }
        }
        
        // JSON文件分类
        if ($fileTypeCounts['json'] > 0) {  // 检查JSON文件数量
            $subCategories[] = [  // 添加JSON子分类
                'vod_id' => 'type_json',  // 视频ID
                'vod_name' => 'JSON文件 (' . $fileTypeCounts['json'] . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        // TXT文件分类（不包括.magnets）
        if ($fileTypeCounts['txt'] > 0) {  // 检查TXT文件数量
            $subCategories[] = [  // 添加TXT子分类
                'vod_id' => 'type_txt',  // 视频ID
                'vod_name' => 'TXT文件 (' . $fileTypeCounts['txt'] . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        // 磁力文件分类（.magnets）
        if ($fileTypeCounts['magnets'] > 0) {  // 检查磁力文件数量
            $subCategories[] = [  // 添加磁力子分类
                'vod_id' => 'type_magnets',  // 视频ID
                'vod_name' => '磁力文件 (' . $fileTypeCounts['magnets'] . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        // M3U文件分类
        $m3uCount = $fileTypeCounts['m3u'] + $fileTypeCounts['m3u8'];  // 计算M3U文件总数
        if ($m3uCount > 0) {  // 检查M3U文件数量
            $subCategories[] = [  // 添加M3U子分类
                'vod_id' => 'type_m3u',  // 视频ID
                'vod_name' => 'M3U文件 (' . $m3uCount . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        // 数据库文件分类
        $dbCount = $fileTypeCounts['db'] + $fileTypeCounts['sqlite'] + $fileTypeCounts['sqlite3'] + $fileTypeCounts['db3'];  // 计算数据库文件总数
        if ($dbCount > 0) {  // 检查数据库文件数量
            $subCategories[] = [  // 添加数据库子分类
                'vod_id' => 'type_db',  // 视频ID
                'vod_name' => '数据库文件 (' . $dbCount . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        // 磁力数据库文件分类（专门处理包含磁力链接的数据库）
        $magnetDbCount = countMagnetDatabaseFiles($allFiles);  // 计算磁力数据库文件数量
        if ($magnetDbCount > 0) {  // 检查磁力数据库文件数量
            $subCategories[] = [  // 添加磁力数据库子分类
                'vod_id' => 'type_magnet_db',  // 视频ID
                'vod_name' => '磁力数据库 (' . $magnetDbCount . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'is_type_category' => true  // 类型分类标志
            ];
        }
        
        if (empty($subCategories)) {  // 如果没有子分类
            return [];  // 返回空数组
        }
        
        return [  // 返回子分类结果
            'is_sub' => true,  // 子分类标志
            'list' => $subCategories,  // 子分类列表
            'page' => 1,  // 当前页码
            'pagecount' => 1,  // 总页数
            'limit' => 20,  // 每页数量
            'total' => count($subCategories),  // 总数量
            'category_name' => '本地资源管理器'  // 分类名称
        ];
    }
    
    // 处理媒体库子分类
    if ($categoryId === 'aggregated_media_library') {  // 检查是否为媒体库子分类
        $mediaFiles = getMediaFiles();  // 获取媒体文件
        $subCategories = [];  // 初始化子分类数组
        
        // 先添加聚合项目
        if (!empty($mediaFiles['aggregated'])) {  // 检查是否有聚合项目
            foreach ($mediaFiles['aggregated'] as $aggregated) {  // 遍历聚合项目
                $subCategories[] = [  // 添加聚合子分类
                    'vod_id' => $aggregated['vod_id'],  // 视频ID
                    'vod_name' => $aggregated['vod_name'],  // 视频名称
                    'vod_pic' => $aggregated['vod_pic'],  // 视频图片
                    'vod_remarks' => $aggregated['vod_remarks'],  // 视频备注
                    'is_aggregated_sub' => true,  // 聚合子分类标志
                    'is_media_aggregated' => true  // 媒体聚合标志
                ];
            }
        }
        
        // 视频子分类
        if (!empty($mediaFiles['video'])) {  // 检查是否有视频文件
            $subCategories[] = [  // 添加视频子分类
                'vod_id' => 'media_video',  // 视频ID
                'vod_name' => '视频文件 (' . count($mediaFiles['video']) . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '视频',  // 视频备注
                'is_media_sub' => true,  // 媒体子分类标志
                'media_type' => 'video'  // 媒体类型
            ];
        }
        
        // 音频子分类
        if (!empty($mediaFiles['audio'])) {  // 检查是否有音频文件
            $subCategories[] = [  // 添加音频子分类
                'vod_id' => 'media_audio',  // 视频ID
                'vod_name' => '音频文件 (' . count($mediaFiles['audio']) . '个)',  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '音频',  // 视频备注
                'is_media_sub' => true,  // 媒体子分类标志
                'media_type' => 'audio'  // 媒体类型
            ];
        }
        
        if (empty($subCategories)) {  // 如果没有子分类
            return [];  // 返回空数组
        }
        
        return [  // 返回子分类结果
            'is_sub' => true,  // 子分类标志
            'list' => $subCategories,  // 子分类列表
            'page' => 1,  // 当前页码
            'pagecount' => 1,  // 总页数
            'limit' => 20,  // 每页数量
            'total' => count($subCategories),  // 总数量
            'category_name' => '媒体库'  // 分类名称
        ];
    }
    
    // 处理类型聚合子分类 - 显示该类型下的所有文件
    if (strpos($categoryId, 'type_') === 0) {  // 检查是否为类型子分类
        $allFiles = getAllFiles();  // 获取所有文件
        $fileType = substr($categoryId, 5);  // 提取文件类型
        
        $subCategories = [];  // 初始化子分类数组
        
        switch ($fileType) {  // 根据文件类型处理
            case 'json':  // JSON文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if ($file['type'] === 'json') {  // 检查文件类型
                        $subCategories[] = [  // 添加JSON文件子分类
                            'vod_id' => 'file_json_' . $index,  // 视频ID
                            'vod_name' => 'JSON ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'json'  // 文件类型
                        ];
                    }
                }
                break;
                
            case 'txt':  // TXT文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if ($file['type'] === 'txt') {  // 检查文件类型
                        $subCategories[] = [  // 添加TXT文件子分类
                            'vod_id' => 'file_txt_' . $index,  // 视频ID
                            'vod_name' => 'TXT ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'txt'  // 文件类型
                        ];
                    }
                }
                break;
                
            case 'magnets':  // 磁力文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if ($file['type'] === 'magnets') {  // 检查文件类型
                        $subCategories[] = [  // 添加磁力文件子分类
                            'vod_id' => 'file_magnets_' . $index,  // 视频ID
                            'vod_name' => 'Magnet ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'magnets'  // 文件类型
                        ];
                    }
                }
                break;
                
            case 'm3u':  // M3U文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if ($file['type'] === 'm3u' || $file['type'] === 'm3u8') {  // 检查文件类型
                        $subCategories[] = [  // 添加M3U文件子分类
                            'vod_id' => 'file_m3u_' . $index,  // 视频ID
                            'vod_name' => 'M3U ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'm3u'  // 文件类型
                        ];
                    }
                }
                break;
                
            case 'db':  // 普通数据库文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && !isMagnetDatabaseFile($file['path'])) {  // 检查文件类型和非磁力数据库
                        $subCategories[] = [  // 添加数据库文件子分类
                            'vod_id' => 'file_db_' . $index,  // 视频ID
                            'vod_name' => 'Database ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'db'  // 文件类型
                        ];
                    }
                }
                break;
                
            case 'magnet_db':  // 磁力数据库文件
                foreach ($allFiles as $index => $file) {  // 遍历所有文件
                    if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($file['path'])) {  // 检查文件类型和磁力数据库
                        $subCategories[] = [  // 添加磁力数据库文件子分类
                            'vod_id' => 'file_magnet_db_' . $index,  // 视频ID
                            'vod_name' => 'Magnet DB ' . $file['filename'],  // 视频名称
                            'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                            'vod_remarks' => '文件',  // 视频备注
                            'is_file_sub' => true,  // 文件子分类标志
                            'file_index' => $index,  // 文件索引
                            'file_type' => 'magnet_db'  // 文件类型
                        ];
                    }
                }
                break;
        }
        
        if (empty($subCategories)) {  // 如果没有子分类
            return [];  // 返回空数组
        }
        
        return [  // 返回子分类结果
            'is_sub' => true,  // 子分类标志
            'list' => $subCategories,  // 子分类列表
            'page' => 1,  // 当前页码
            'pagecount' => 1,  // 总页数
            'limit' => 20,  // 每页数量
            'total' => count($subCategories),  // 总数量
            'category_name' => getTypeCategoryName($fileType)  // 分类名称
        ];
    }
    
    // 处理文件子分类（三级分类） - 显示具体文件内容
    if (strpos($categoryId, 'file_') === 0) {  // 检查是否为文件子分类
        $parts = explode('_', $categoryId);  // 分割分类ID
        if (count($parts) >= 3) {  // 检查部分数量
            $fileType = $parts[1];  // 提取文件类型
            $fileIndex = intval($parts[2]);  // 提取文件索引
            
            $allFiles = getAllFiles();  // 获取所有文件
            
            if (!isset($allFiles[$fileIndex])) {  // 检查文件是否存在
                return [];  // 文件不存在返回空数组
            }
            
            $targetFile = $allFiles[$fileIndex];  // 获取目标文件
            
            // 验证文件类型是否匹配
            $isValidType = false;  // 初始化有效类型标志
            switch ($fileType) {  // 根据文件类型检查
                case 'json':  // JSON文件
                    $isValidType = ($targetFile['type'] === 'json');  // 检查类型匹配
                    break;
                case 'txt':  // TXT文件
                    $isValidType = ($targetFile['type'] === 'txt');  // 检查类型匹配
                    break;
                case 'magnets':  // 磁力文件
                    $isValidType = ($targetFile['type'] === 'magnets');  // 检查类型匹配
                    break;
                case 'm3u':  // M3U文件
                    $isValidType = ($targetFile['type'] === 'm3u' || $targetFile['type'] === 'm3u8');  // 检查类型匹配
                    break;
                case 'db':  // 普通数据库文件
                    $isValidType = in_array($targetFile['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && !isMagnetDatabaseFile($targetFile['path']);  // 检查类型匹配和非磁力数据库
                    break;
                case 'magnet_db':  // 磁力数据库文件
                    $isValidType = in_array($targetFile['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($targetFile['path']);  // 检查类型匹配和磁力数据库
                    break;
            }
            
            if (!$isValidType) {  // 如果类型不匹配
                return [];  // 返回空数组
            }
            
            $categoryVideos = [];  // 初始化分类视频数组
            
            if (file_exists($targetFile['path'])) {  // 检查文件是否存在
                switch ($targetFile['type']) {  // 根据文件类型解析
                    case 'json':  // JSON文件
                        $categoryVideos = parseJsonFile($targetFile['path']);  // 解析JSON文件
                        break;
                    case 'txt':  // TXT文件
                    case 'magnets':  // 磁力文件
                        $categoryVideos = parseTxtFile($targetFile['path']);  // 解析TXT文件
                        break;
                    case 'm3u':  // M3U文件
                        $categoryVideos = parseM3uFile($targetFile['path']);  // 解析M3U文件
                        break;
                    case 'db':  // 数据库文件
                    case 'sqlite':  // SQLite文件
                    case 'sqlite3':  // SQLite3文件
                    case 'db3':  // DB3文件
                        $categoryVideos = parseDatabaseFile($targetFile['path']);  // 解析数据库文件
                        break;
                }
            }
            
            if (empty($categoryVideos)) {  // 如果没有视频
                return [];  // 返回空数组
            }
            
            $pageSize = 10;  // 每页数量
            $total = count($categoryVideos);  // 总数量
            $pageCount = ceil($total / $pageSize);  // 总页数
            $currentPage = intval($page);  // 当前页码
            
            if ($currentPage < 1) $currentPage = 1;  // 页码最小为1
            if ($currentPage > $pageCount) $currentPage = $pageCount;  // 页码最大为总页数
            
            $start = ($currentPage - 1) * $pageSize;  // 计算起始位置
            $pagedVideos = array_slice($categoryVideos, $start, $pageSize);  // 分页视频
            
            $formattedVideos = [];  // 初始化格式化视频数组
            foreach ($pagedVideos as $video) {  // 遍历分页视频
                $formattedVideos[] = formatVideoItem($video);  // 格式化视频项
            }
            
            return [  // 返回分类结果
                'page' => $currentPage,  // 当前页码
                'pagecount' => $pageCount,  // 总页数
                'limit' => $pageSize,  // 每页数量
                'total' => $total,  // 总数量
                'list' => $formattedVideos,  // 视频列表
                'category_name' => $targetFile['filename']  // 分类名称
            ];
        }
    }
    
    // 保持原有的媒体文件处理逻辑
    if (strpos($categoryId, 'media_aggregated_') === 0) {  // 检查是否为媒体聚合分类
        $mediaFiles = getMediaFiles();  // 获取媒体文件
        
        foreach ($mediaFiles['aggregated'] as $aggregated) {  // 遍历聚合项目
            if ($aggregated['vod_id'] === $categoryId) {  // 检查视频ID匹配
                return [  // 返回聚合项目结果
                    'page' => 1,  // 当前页码
                    'pagecount' => 1,  // 总页数
                    'limit' => 1,  // 每页数量
                    'total' => 1,  // 总数量
                    'list' => [$aggregated],  // 视频列表
                    'category_name' => $aggregated['vod_name']  // 分类名称
                ];
            }
        }
        
        return [];  // 未找到返回空数组
    }
    
    if (strpos($categoryId, 'media_') === 0) {  // 检查是否为媒体分类
        $mediaType = substr($categoryId, 6);  // 提取媒体类型
        $mediaFiles = getMediaFiles();  // 获取媒体文件
        
        if (!isset($mediaFiles[$mediaType]) || empty($mediaFiles[$mediaType])) {  // 检查媒体类型是否存在
            return [];  // 不存在返回空数组
        }
        
        $mediaList = [];  // 初始化媒体列表
        foreach ($mediaFiles[$mediaType] as $index => $file) {  // 遍历媒体文件
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            
            $mediaList[] = [  // 添加到媒体列表
                'vod_id' => 'media_' . $mediaType . '_' . $index,  // 视频ID
                'vod_name' => $file['filename'],  // 视频名称
                'vod_pic' => getMediaThumbnail($file, $mediaType),  // 视频图片
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,  // 视频备注
                'vod_year' => date('Y', filemtime($file['path'])),  // 视频年份
                'vod_area' => '本地文件',  // 视频地区
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),  // 视频内容
                'vod_play_from' => $mediaType === 'video' ? '本地视频' : '本地音频',  // 播放来源
                'vod_play_url' => 'Play$' . urlencode($file['path'])  // 播放URL
            ];
        }
        
        $pageSize = 10;  // 每页数量
        $total = count($mediaList);  // 总数量
        $pageCount = ceil($total / $pageSize);  // 总页数
        $currentPage = intval($page);  // 当前页码
        
        if ($currentPage < 1) $currentPage = 1;  // 页码最小为1
        if ($currentPage > $pageCount) $currentPage = $pageCount;  // 页码最大为总页数
        
        $start = ($currentPage - 1) * $pageSize;  // 计算起始位置
        $pagedMedia = array_slice($mediaList, $start, $pageSize);  // 分页媒体
        
        return [  // 返回媒体结果
            'page' => $currentPage,  // 当前页码
            'pagecount' => $pageCount,  // 总页数
            'limit' => $pageSize,  // 每页数量
            'total' => $total,  // 总数量
            'list' => $pagedMedia,  // 媒体列表
            'category_name' => ($mediaType === 'video' ? '视频文件' : '音频文件')  // 分类名称
        ];
    }
    
    return [];  // 默认返回空数组
}

// 辅助函数：获取类型分类名称
function getTypeCategoryName($fileType) {  // 获取类型分类名称
    $names = [  // 类型名称映射
        'json' => 'JSON文件',  // JSON文件名称
        'txt' => 'TXT文件',  // TXT文件名称
        'magnets' => '磁力文件',  // 磁力文件名称
        'm3u' => 'M3U文件',   // M3U文件名称
        'db' => '数据库文件',  // 数据库文件名称
        'magnet_db' => '磁力数据库'  // 磁力数据库名称
    ];
    
    return $names[$fileType] ?? '未知分类';  // 返回类型名称或未知分类
}

// 辅助函数：检测是否为磁力数据库文件
function isMagnetDatabaseFile($filePath) {  // 检测是否为磁力数据库文件
    if (!file_exists($filePath) || !extension_loaded('pdo_sqlite')) {  // 检查文件存在和扩展加载
        return false;  // 返回false
    }
    
    try {  // 尝试检测
        $db = new PDO("sqlite:" . $filePath);  // 创建数据库连接
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // 设置错误模式
        
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
        
        foreach ($tables as $table) {  // 遍历所有表
            if (strpos($table, 'sqlite_') === 0) continue;  // 跳过系统表
            
            // 检查表结构是否有data字段
            $fieldInfo = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
            $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
            
            if (in_array('data', $fieldNames) || in_array('json_data', $fieldNames)) {  // 检查数据字段
                // 检查数据内容是否包含JSON格式的磁力链接
                $sampleData = $db->query("SELECT data FROM $table LIMIT 1")->fetch(PDO::FETCH_COLUMN);  // 获取样本数据
                if ($sampleData && strpos($sampleData, '"magnet":') !== false) {  // 检查磁力链接
                    $db = null;  // 关闭数据库连接
                    return true;  // 返回true
                }
            }
        }
        
        $db = null;  // 关闭数据库连接
        return false;  // 返回false
        
    } catch (PDOException $e) {  // 捕获异常
        return false;  // 返回false
    }
}

// 辅助函数：统计磁力数据库文件数量
function countMagnetDatabaseFiles($allFiles) {  // 统计磁力数据库文件数量
    $count = 0;  // 初始化计数
    foreach ($allFiles as $file) {  // 遍历所有文件
        if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && isMagnetDatabaseFile($file['path'])) {  // 检查文件类型和磁力数据库
            $count++;  // 增加计数
        }
    }
    return $count;  // 返回计数
}
// ==================== 第十部分：首页和搜索功能 ====================

function getHome() {  // 获取首页
    $categoryList = getCategoryList();  // 获取分类列表
    
    if (empty($categoryList)) {  // 如果没有分类
        return [];  // 返回空数组
    }
    
    return ['class' => $categoryList];  // 返回分类列表
}

function formatVideoItem($video) {  // 格式化视频项
    return [  // 返回格式化视频
        'vod_id' => $video['vod_id'] ?? '',  // 视频ID
        'vod_name' => $video['vod_name'] ?? 'Unknown Video',  // 视频名称
        'vod_pic' => $video['vod_pic'] ?? 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',  // 视频备注
        'vod_year' => $video['vod_year'] ?? '',  // 视频年份
        'vod_area' => $video['vod_area'] ?? 'China'  // 视频地区
    ];
}

// 搜索匹配函数
function searchMatch($text, $keyword) {  // 搜索匹配
    if (empty($text) || empty($keyword)) return false;  // 空文本或关键词返回false
    
    $text = strtolower(trim($text));  // 文本转为小写并去除空格
    $keyword = strtolower(trim($keyword));  // 关键词转为小写并去除空格
    
    // 完全匹配
    if (strpos($text, $keyword) !== false) return true;  // 完全匹配返回true
    
    // 分词匹配
    $keywords = preg_split('/\s+/', $keyword);  // 分割关键词
    if (count($keywords) > 1) {  // 如果有多个关键词
        $matchCount = 0;  // 初始化匹配计数
        foreach ($keywords as $kw) {  // 遍历关键词
            if (strpos($text, $kw) !== false) {  // 检查部分匹配
                $matchCount++;  // 增加匹配计数
            }
        }
        // 如果所有关键词都匹配，返回true
        if ($matchCount == count($keywords)) {  // 检查完全匹配
            return true;  // 返回true
        }
    }
    
    // 字符包含匹配
    $keywordLength = mb_strlen($keyword, 'UTF-8');  // 获取关键词长度
    if ($keywordLength > 1) {  // 如果关键词长度大于1
        for ($i = 0; $i < $keywordLength; $i++) {  // 遍历每个字符
            $char = mb_substr($keyword, $i, 1, 'UTF-8');  // 提取字符
            if (mb_strpos($text, $char, 0, 'UTF-8') === false) return false;  // 检查字符包含
        }
        return true;  // 所有字符都包含返回true
    }
    
    return false;  // 默认返回false
}

// 主搜索功能
function search($keyword, $page) {  // 搜索功能
    if (empty($keyword)) {  // 如果关键词为空
        return [];  // 返回空数组
    }
    
    $searchResults = [];  // 初始化搜索结果
    $allFiles = getAllFiles();  // 获取所有文件
    $mediaFiles = getMediaFiles();  // 获取媒体文件
    $categoryList = getCategoryList();  // 获取分类列表
    
    $searchLimit = 20;  // 搜索文件限制
    $searchedFiles = 0;  // 已搜索文件计数
    
    // 搜索数据文件
    foreach ($allFiles as $fileIndex => $file) {  // 遍历所有文件
        if ($searchedFiles >= $searchLimit) break;  // 达到搜索限制时停止
        
        if (!file_exists($file['path'])) continue;  // 文件不存在则跳过
        
        $fileSize = filesize($file['path']);  // 获取文件大小
        if ($fileSize > 60 * 1024 * 1024) continue;  // 跳过大于60MB的文件
        
        $videoList = [];  // 初始化视频列表
        try {  // 尝试解析文件
            switch ($file['type']) {  // 根据文件类型解析
                case 'json':  // JSON文件
                    $videoList = parseJsonFile($file['path']);  // 解析JSON文件
                    break;
                case 'txt':  // TXT文件
                case 'magnets':  // 磁力文件
                    $videoList = parseTxtFile($file['path']);  // 解析TXT文件
                    break;
                case 'm3u':  // M3U文件
                    $videoList = parseM3uFile($file['path']);  // 解析M3U文件
                    break;
                case 'db':  // 数据库文件
                case 'sqlite':  // SQLite文件
                case 'sqlite3':  // SQLite3文件
                case 'db3':  // DB3文件
                    $videoList = parseDatabaseFile($file['path']);  // 解析数据库文件
                    break;
                default:  // 默认情况
                    continue 2;  // 跳到下一个文件
            }
        } catch (Exception $e) {  // 捕获异常
            continue;  // 继续下一个文件
        }
        
        if (!is_array($videoList) || empty($videoList)) continue;  // 跳过无效视频列表
        
        $fileMatchCount = 0;  // 文件匹配计数
        foreach ($videoList as $videoIndex => $video) {  // 遍历视频列表
            $videoName = $video['vod_name'] ?? '';  // 获取视频名称
            if (empty($videoName)) continue;  // 跳过空名称
            
            if (searchMatch($videoName, $keyword)) {  // 检查搜索匹配
                $formattedVideo = formatVideoItem($video);  // 格式化视频
                if (isset($video['vod_play_from']) && isset($video['vod_play_url'])) {  // 检查播放信息
                    $formattedVideo['vod_play_from'] = $video['vod_play_from'];  // 设置播放来源
                    $formattedVideo['vod_play_url'] = $video['vod_play_url'];  // 设置播放URL
                }
                $searchResults[] = $formattedVideo;  // 添加到搜索结果
                $fileMatchCount++;  // 增加文件匹配计数
                
                if ($fileMatchCount >= 90) break;  // 单个文件最多90个结果
                if (count($searchResults) >= 300) break 2;  // 总结果达到300时完全停止
            }
        }
        
        $searchedFiles++;  // 增加已搜索文件计数
    }
    
    // 搜索视频媒体文件
    foreach ($mediaFiles['video'] as $index => $file) {  // 遍历视频文件
        if (searchMatch($file['filename'], $keyword)) {  // 检查搜索匹配
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            
            $searchResults[] = [  // 添加到搜索结果
                'vod_id' => 'media_video_' . $index,  // 视频ID
                'vod_name' => $file['filename'],  // 视频名称
                'vod_pic' => getMediaThumbnail($file, 'video'),  // 视频图片
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,  // 视频备注
                'vod_year' => date('Y', filemtime($file['path'])),  // 视频年份
                'vod_area' => '本地文件',  // 视频地区
                'vod_play_from' => '本地视频',  // 播放来源
                'vod_play_url' => 'Play$' . urlencode($file['path'])  // 播放URL
            ];
            
            if (count($searchResults) >= 300) break;  // 总结果达到300时停止
        }
    }
    
    // 搜索音频媒体文件
    foreach ($mediaFiles['audio'] as $index => $file) {  // 遍历音频文件
        if (searchMatch($file['filename'], $keyword)) {  // 检查搜索匹配
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            
            $searchResults[] = [  // 添加到搜索结果
                'vod_id' => 'media_audio_' . $index,  // 视频ID
                'vod_name' => $file['filename'],  // 视频名称
                'vod_pic' => getMediaThumbnail($file, 'audio'),  // 视频图片
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,  // 视频备注
                'vod_year' => date('Y', filemtime($file['path'])),  // 视频年份
                'vod_area' => '本地文件',  // 视频地区
                'vod_play_from' => '本地音频',  // 播放来源
                'vod_play_url' => 'Play$' . urlencode($file['path'])  // 播放URL
            ];
            
            if (count($searchResults) >= 300) break;  // 总结果达到300时停止
        }
    }
    
    // 搜索分类名称（支持新的类型分类）
    foreach ($categoryList as $category) {  // 遍历分类列表
        if (searchMatch($category['type_name'], $keyword)) {  // 检查搜索匹配
            $searchResults[] = [  // 添加到搜索结果
                'vod_id' => $category['type_id'],  // 视频ID
                'vod_name' => '[分类] ' . $category['type_name'],  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => '分类',  // 视频备注
                'vod_year' => '',  // 视频年份
                'vod_area' => '分类',  // 视频地区
                'is_category' => true  // 分类标志
            ];
        }
    }
    
    if (empty($searchResults)) {  // 如果没有搜索结果
        return [];  // 返回空数组
    }
    
    // 去重
    $dedupResults = [];  // 初始化去重结果
    $existingIds = [];  // 初始化存在ID
    foreach ($searchResults as $video) {  // 遍历搜索结果
        $videoId = $video['vod_id'] ?? $video['vod_name'];  // 获取视频ID
        if (!in_array($videoId, $existingIds)) {  // 检查是否已存在
            $dedupResults[] = $video;  // 添加到去重结果
            $existingIds[] = $videoId;  // 添加到存在ID
        }
    }
    $searchResults = $dedupResults;  // 更新搜索结果
    
    // 分页处理
    $pageSize = 10;  // 每页数量
    $total = count($searchResults);  // 总数量
    $pageCount = ceil($total / $pageSize);  // 总页数
    $currentPage = intval($page);  // 当前页码
    
    if ($currentPage < 1) $currentPage = 1;  // 页码最小为1
    if ($currentPage > $pageCount) $currentPage = $pageCount;  // 页码最大为总页数
    
    $start = ($currentPage - 1) * $pageSize;  // 计算起始位置
    $pagedResults = array_slice($searchResults, $start, $pageSize);  // 分页结果
    
    return [  // 返回搜索结果
        'page' => $currentPage,  // 当前页码
        'pagecount' => $pageCount,  // 总页数
        'limit' => $pageSize,  // 每页数量
        'total' => $total,  // 总数量
        'list' => $pagedResults  // 结果列表
    ];
}
// ==================== 第十一部分：播放和详情功能 ====================

function getPlay($flag, $id) {  // 获取播放信息
    $playId = urldecode($id);  // URL解码播放ID
    
    // 处理磁力链接
    if (strpos($playId, 'magnet:') === 0) {  // 检查是否为磁力链接
        return [  // 返回磁力播放信息
            'parse' => 0,  // 解析标志
            'playUrl' => '',  // 播放URL
            'url' => $playId,  // 实际URL
            'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],  // 请求头
            'type' => 'magnet'  // 播放类型
        ];
    }
    
    // 处理电驴链接
    if (strpos($playId, 'ed2k://') === 0) {  // 检查是否为电驴链接
        return [  // 返回电驴播放信息
            'parse' => 0,  // 解析标志
            'playUrl' => '',  // 播放URL
            'url' => $playId,  // 实际URL
            'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],  // 请求头
            'type' => 'ed2k'  // 播放类型
        ];
    }
    
    return [  // 返回普通播放信息
        'parse' => 0,  // 解析标志
        'playUrl' => '',  // 播放URL
        'url' => $playId,  // 实际URL
        'header' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],  // 请求头
        'type' => 'video'  // 播放类型
    ];
}

function getDetail($videoId) {  // 获取详情
    $idArray = explode(',', $videoId);  // 分割视频ID
    $result = [];  // 初始化结果数组
    
    foreach ($idArray as $id) {  // 遍历视频ID
        $video = findVideoById($id);  // 查找视频
        if ($video) {  // 如果找到视频
            $result[] = formatVideoDetail($video);  // 格式化视频详情并添加到结果
        } else {  // 未找到视频
            $result[] = [  // 添加默认视频详情
                'vod_id' => $id,  // 视频ID
                'vod_name' => 'Video ' . $id,  // 视频名称
                'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                'vod_remarks' => 'HD',  // 视频备注
                'vod_content' => 'Video detail content',  // 视频内容
                'vod_play_from' => 'Online',  // 播放来源
                'vod_play_url' => 'Play$https://example.com/video'  // 播放URL
            ];
        }
    }
    
    return ['list' => $result];  // 返回结果列表
}

function findVideoById($id) {  // 根据ID查找视频
    $allFiles = getAllFiles();  // 获取所有文件
    $mediaFiles = getMediaFiles();  // 获取媒体文件
    
    // 处理媒体聚合项目
    if (strpos($id, 'media_aggregated_') === 0) {  // 检查是否为媒体聚合项目
        foreach ($mediaFiles['aggregated'] as $aggregated) {  // 遍历聚合项目
            if ($aggregated['vod_id'] === $id) {  // 检查视频ID匹配
                return $aggregated;  // 返回聚合项目
            }
        }
    }
    
    // 处理分类
    if (strpos($id, 'aggregated') === 0) {  // 检查是否为聚合分类
        $categoryList = getCategoryList();  // 获取分类列表
        foreach ($categoryList as $category) {  // 遍历分类列表
            if ($category['type_id'] === $id) {  // 检查分类ID匹配
                return [  // 返回分类信息
                    'vod_id' => $id,  // 视频ID
                    'vod_name' => $category['type_name'],  // 视频名称
                    'vod_pic' => 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
                    'vod_remarks' => '分类',  // 视频备注
                    'vod_year' => '',  // 视频年份
                    'vod_area' => '分类',  // 视频地区
                    'vod_content' => '分类: ' . $category['type_name'],  // 视频内容
                    'vod_play_from' => '分类',  // 播放来源
                    'vod_play_url' => ''  // 播放URL
                ];
            }
        }
    }
    
    // 处理媒体文件
    if (strpos($id, 'media_video_') === 0) {  // 检查是否为视频文件
        $index = intval(substr($id, 12));  // 提取索引
        if (isset($mediaFiles['video'][$index])) {  // 检查视频文件存在
            $file = $mediaFiles['video'][$index];  // 获取文件信息
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            
            return [  // 返回视频文件信息
                'vod_id' => 'media_video_' . $index,  // 视频ID
                'vod_name' => $file['filename'],  // 视频名称
                'vod_pic' => getMediaThumbnail($file, 'video'),  // 视频图片
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,  // 视频备注
                'vod_year' => date('Y', filemtime($file['path'])),  // 视频年份
                'vod_area' => '本地文件',  // 视频地区
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),  // 视频内容
                'vod_play_from' => '本地视频',  // 播放来源
                'vod_play_url' => 'Play$' . urlencode($file['path'])  // 播放URL
            ];
        }
    }
    
    if (strpos($id, 'media_audio_') === 0) {  // 检查是否为音频文件
        $index = intval(substr($id, 12));  // 提取索引
        if (isset($mediaFiles['audio'][$index])) {  // 检查音频文件存在
            $file = $mediaFiles['audio'][$index];  // 获取文件信息
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            
            return [  // 返回音频文件信息
                'vod_id' => 'media_audio_' . $index,  // 视频ID
                'vod_name' => $file['filename'],  // 视频名称
                'vod_pic' => getMediaThumbnail($file, 'audio'),  // 视频图片
                'vod_remarks' => strtoupper($file['type']) . ' · ' . $fileSize,  // 视频备注
                'vod_year' => date('Y', filemtime($file['path'])),  // 视频年份
                'vod_area' => '本地文件',  // 视频地区
                'vod_content' => '文件路径: ' . $file['relative_path'] . "\n大小: " . $fileSize . "\n类型: " . strtoupper($file['type']),  // 视频内容
                'vod_play_from' => '本地音频',  // 播放来源
                'vod_play_url' => 'Play$' . urlencode($file['path'])  // 播放URL
            ];
        }
    }
    
    // 处理聚合TXT文件
    if (strpos($id, 'txt_aggregated_') === 0) {  // 检查是否为聚合TXT文件
        $fileHash = substr($id, 15);  // 提取文件哈希
        foreach ($allFiles as $file) {  // 遍历所有文件
            if (($file['type'] === 'txt' || $file['type'] === 'magnets') && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                return findTxtVideoByLine($file['path'], 0);  // 查找TXT视频
            }
        }
    } 
    // 处理单资源TXT文件
    elseif (strpos($id, 'txt_single_') === 0) {  // 检查是否为单资源TXT文件
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[2];  // 提取文件哈希
            $lineNumber = $parts[3];  // 提取行号
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (($file['type'] === 'txt' || $file['type'] === 'magnets') && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findSingleTxtVideoByLine($file['path'], $lineNumber);  // 查找单资源TXT视频
                }
            }
        }
    } 
    // 处理聚合M3U文件
    elseif (strpos($id, 'm3u_aggregated_') === 0) {  // 检查是否为聚合M3U文件
        $fileHash = substr($id, 15);  // 提取文件哈希
        foreach ($allFiles as $file) {  // 遍历所有文件
            if (in_array($file['type'], ['m3u', 'm3u8']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                return findM3uVideoByLine($file['path'], 0);  // 查找M3U视频
            }
        }
    } 
    // 处理单资源M3U文件
    elseif (strpos($id, 'm3u_single_') === 0) {  // 检查是否为单资源M3U文件
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[2];  // 提取文件哈希
            $lineNumber = $parts[3];  // 提取行号
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['m3u', 'm3u8']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findSingleM3uVideoByLine($file['path'], $lineNumber);  // 查找单资源M3U视频
                }
            }
        }
    } 
    // 处理JSON数据库文件
    elseif (strpos($id, 'json_db_') === 0) {  // 检查是否为JSON数据库文件
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[2];  // 提取文件哈希
            $tableName = $parts[3];  // 提取表名
            $videoIndex = $parts[4];  // 提取视频索引
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);  // 查找数据库视频
                }
            }
        }
    } 
    // 处理普通数据库文件
    elseif (strpos($id, 'db_') === 0) {  // 检查是否为普通数据库文件
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[1];  // 提取文件哈希
            $tableName = $parts[2];  // 提取表名
            $videoIndex = $parts[3];  // 提取视频索引
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);  // 查找数据库视频
                }
            }
        }
    } 
    // 处理视频分类数据库
    elseif (strpos($id, 'video_') === 0) {  // 检查是否为视频分类数据库
        $videoID = substr($id, 6);  // 提取视频ID
        return findCategoryVideoById($videoID);  // 查找分类视频
    } 
    // 处理自动检测数据库
    elseif (strpos($id, 'auto_') === 0) {  // 检查是否为自动检测数据库
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[1];  // 提取文件哈希
            $tableName = $parts[2];  // 提取表名
            $videoIndex = $parts[3];  // 提取视频索引
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);  // 查找数据库视频
                }
            }
        }
    } 
    // 处理JSON表数据
    elseif (strpos($id, 'json_') === 0) {  // 检查是否为JSON表数据
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[1];  // 提取文件哈希
            $tableName = $parts[2];  // 提取表名
            $videoIndex = $parts[3];  // 提取视频索引
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);  // 查找数据库视频
                }
            }
        }
    } 
    // 处理直播频道
    elseif (strpos($id, 'live_') === 0) {  // 检查是否为直播频道
        $parts = explode('_', $id);  // 分割ID
        if (count($parts) >= 4) {  // 检查部分数量
            $fileHash = $parts[1];  // 提取文件哈希
            $tableName = $parts[2];  // 提取表名
            $videoIndex = $parts[3];  // 提取视频索引
            
            foreach ($allFiles as $file) {  // 遍历所有文件
                if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3']) && md5($file['path']) === $fileHash) {  // 检查文件类型和哈希匹配
                    return findDatabaseVideoByIndex($file['path'], $tableName, $videoIndex);  // 查找数据库视频
                }
            }
        }
    } 
    // 处理JSON文件中的视频
    else {  // 默认情况
        foreach ($allFiles as $file) {  // 遍历所有文件
            if ($file['type'] === 'json') {  // 检查JSON文件
                $videoList = parseJsonFile($file['path']);  // 解析JSON文件
                if (is_array($videoList)) {  // 检查视频列表
                    foreach ($videoList as $video) {  // 遍历视频列表
                        if (isset($video['vod_id']) && $video['vod_id'] == $id) {  // 检查视频ID匹配
                            return $video;  // 返回视频
                        }
                    }
                }
            }
        }
    }
    
    return null;  // 未找到返回null
}
// ==================== 第十二部分：查找辅助函数 ====================

// 查找聚合TXT视频详情
function findTxtVideoByLine($filePath, $targetLine) {  // 查找聚合TXT视频
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return null;  // 文件不存在返回null
    }
    
    $handle = @fopen($filePath, 'r');  // 打开文件
    if (!$handle) {  // 如果打开失败
        return null;  // 返回null
    }
    
    $currentLine = 0;  // 初始化当前行号
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    // 读取所有行
    $allLines = [];  // 初始化所有行数组
    while (($line = fgets($handle)) !== false) {  // 逐行读取
        $currentLine++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;  // 跳过空行和注释行
        
        $link = '';  // 初始化链接
        $name = '';  // 初始化名称
        $isMagnet = false;  // 初始化磁力链接标志
        $isEd2k = false;  // 初始化电驴链接标志
        
        if (isMagnetLink($line)) {  // 检查是否为磁力链接
            $link = $line;  // 设置链接
            $name = getFileNameFromMagnet($link);  // 从磁力链接提取文件名
            $isMagnet = true;  // 设置磁力链接标志
        }
        elseif (isEd2kLink($line)) {  // 检查是否为电驴链接
            $link = $line;  // 设置链接
            $name = getFileNameFromEd2k($link);  // 从电驴链接提取文件名
            $isEd2k = true;  // 设置电驴链接标志
        }
        else {  // 不是纯链接
            $separators = [',', "\t", '|', '$', '#'];  // 分隔符数组
            $separatorPos = false;  // 初始化分隔符位置
            
            foreach ($separators as $sep) {  // 遍历分隔符
                $pos = strpos($line, $sep);  // 查找分隔符位置
                if ($pos !== false) {  // 如果找到分隔符
                    $separatorPos = $pos;  // 设置分隔符位置
                    break;  // 跳出循环
                }
            }
            
            if ($separatorPos !== false) {  // 如果找到分隔符
                $namePart = trim(substr($line, 0, $separatorPos));  // 提取名称部分
                $linkPart = trim(substr($line, $separatorPos + 1));  // 提取链接部分
                
                if (isMagnetLink($linkPart)) {  // 检查链接部分是否为磁力链接
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);  // 设置名称
                    $isMagnet = true;  // 设置磁力链接标志
                } elseif (isEd2kLink($linkPart)) {  // 检查链接部分是否为电驴链接
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);  // 设置名称
                    $isEd2k = true;  // 设置电驴链接标志
                } elseif (filter_var($linkPart, FILTER_VALIDATE_URL)) {  // 检查链接部分是否为有效URL
                    $link = $linkPart;  // 设置链接
                    $name = !empty($namePart) ? $namePart : 'Online Video';  // 设置名称
                }
            } else {  // 没有分隔符
                // 如果没有分隔符，整行作为链接，自动生成名称
                if (isMagnetLink($line)) {  // 检查整行是否为磁力链接
                    $link = $line;  // 设置链接
                    $name = getFileNameFromMagnet($line);  // 从磁力链接提取文件名
                    $isMagnet = true;  // 设置磁力链接标志
                } elseif (isEd2kLink($line)) {  // 检查整行是否为电驴链接
                    $link = $line;  // 设置链接
                    $name = getFileNameFromEd2k($line);  // 从电驴链接提取文件名
                    $isEd2k = true;  // 设置电驴链接标志
                } elseif (filter_var($line, FILTER_VALIDATE_URL)) {  // 检查整行是否为有效URL
                    $link = $line;  // 设置链接
                    $name = 'Online Video';  // 设置默认名称
                }
            }
        }
        
        if (!empty($link) && !empty($name)) {  // 验证链接和名称
            $allLines[] = [  // 添加到所有行数组
                'name' => $name,  // 名称
                'link' => $link,  // 链接
                'is_magnet' => $isMagnet,  // 磁力链接标志
                'is_ed2k' => $isEd2k,  // 电驴链接标志
                'line_number' => $currentLine  // 行号
            ];
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    
    if (empty($allLines)) {  // 如果没有有效行
        return null;  // 返回null
    }
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);  // 获取文件名
    $imgIndex = 0 % count($defaultImages);  // 计算图片索引
    
    // 构建播放列表 - 使用实际名字作为线路名称
    $playUrls = [];  // 初始化播放URL数组
    foreach ($allLines as $index => $lineData) {  // 遍历所有行数据
        $playSource = $lineData['name'];  // 获取播放源名称
        // 如果是磁力链接或电驴链接，在名称后添加类型标识
        if ($lineData['is_magnet']) {  // 如果是磁力链接
            $playSource = $lineData['name'] . ' [磁力]';  // 添加磁力标识
        } elseif ($lineData['is_ed2k']) {  // 如果是电驴链接
            $playSource = $lineData['name'] . ' [电驴]';  // 添加电驴标识
        }
        
        $playUrls[] = $playSource . '$' . $lineData['link'];  // 添加到播放URL数组
    }
    
    $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
    
    $video = [  // 构建视频信息
        'vod_id' => 'txt_aggregated_' . md5($filePath),  // 视频ID
        'vod_name' => '[聚合] ' . $fileName,  // 视频名称
        'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
        'vod_remarks' => count($allLines) . '个资源',  // 视频备注
        'vod_year' => date('Y'),  // 视频年份
        'vod_area' => 'China',  // 视频地区
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allLines) . " 个资源\n文件路径: " . $filePath . "\n【聚合模式】所有资源合并到一个项目中",  // 视频内容
        'vod_play_from' => '资源列表',  // 播放来源
        'vod_play_url' => $playUrlStr,  // 播放URL
        'is_aggregated' => true  // 聚合标志
    ];
    
    return $video;  // 返回视频
}

// 查找单资源TXT视频
function findSingleTxtVideoByLine($filePath, $targetLine) {  // 查找单资源TXT视频
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return null;  // 文件不存在返回null
    }
    
    $handle = @fopen($filePath, 'r');  // 打开文件
    if (!$handle) {  // 如果打开失败
        return null;  // 返回null
    }
    
    $currentLine = 0;  // 初始化当前行号
    $video = null;  // 初始化视频
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    // 处理BOM头
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    while (($line = fgets($handle)) !== false) {  // 逐行读取
        $currentLine++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        
        // 跳过空行和注释行
        if ($line === '' || $line[0] === '#' || $line[0] === ';' || $line[0] === '//') {  // 检查空行或注释
            continue;  // 跳过当前行
        }
        
        if ($currentLine == $targetLine) {  // 检查目标行
            $link = '';  // 初始化链接
            $name = '';  // 初始化名称
            $isMagnet = false;  // 初始化磁力链接标志
            $isEd2k = false;  // 初始化电驴链接标志
            
            // 使用与parseTxtFile相同的解析逻辑
            if (isMagnetLink($line)) {  // 检查是否为磁力链接
                $link = $line;  // 设置链接
                $name = getFileNameFromMagnet($line);  // 从磁力链接提取文件名
                $isMagnet = true;  // 设置磁力链接标志
            }
            elseif (isEd2kLink($line)) {  // 检查是否为电驴链接
                $link = $line;  // 设置链接
                $name = getFileNameFromEd2k($link);  // 从电驴链接提取文件名
                $isEd2k = true;  // 设置电驴链接标志
            }
            else {  // 不是纯链接
                $separators = [',', "\t", '|', '$', '#', ';', '：', ' '];  // 分隔符数组
                $separatorPos = false;  // 初始化分隔符位置
                $usedSeparator = '';  // 初始化使用的分隔符
                
                foreach ($separators as $sep) {  // 遍历分隔符
                    $pos = strpos($line, $sep);  // 查找分隔符位置
                    if ($pos !== false) {  // 如果找到分隔符
                        $separatorPos = $pos;  // 设置分隔符位置
                        $usedSeparator = $sep;  // 设置使用的分隔符
                        break;  // 跳出循环
                    }
                }
                
                if ($separatorPos !== false) {  // 如果找到分隔符
                    $namePart = trim(substr($line, 0, $separatorPos));  // 提取名称部分
                    $linkPart = trim(substr($line, $separatorPos + strlen($usedSeparator)));  // 提取链接部分
                    
                    if (isMagnetLink($linkPart)) {  // 检查链接部分是否为磁力链接
                        $link = $linkPart;  // 设置链接
                        $name = !empty($namePart) ? $namePart : getFileNameFromMagnet($linkPart);  // 设置名称
                        $isMagnet = true;  // 设置磁力链接标志
                    } elseif (isEd2kLink($linkPart)) {  // 检查链接部分是否为电驴链接
                        $link = $linkPart;  // 设置链接
                        $name = !empty($namePart) ? $namePart : getFileNameFromEd2k($linkPart);  // 设置名称
                        $isEd2k = true;  // 设置电驴链接标志
                    } elseif (isValidLink($linkPart)) {  // 检查链接部分是否有效
                        $link = $linkPart;  // 设置链接
                        $name = !empty($namePart) ? $namePart : 'Online Video';  // 设置名称
                    }
                } else {  // 没有分隔符
                    if (isValidLink($line)) {  // 检查整行是否为有效链接
                        $link = $line;  // 设置链接
                        $name = 'Online Video';  // 设置默认名称
                    }
                }
            }
            
            if (!empty($link) && !empty($name) && isValidLink($link)) {  // 验证链接和名称
                $imgIndex = $currentLine % count($defaultImages);  // 计算图片索引
                
                $playSource = 'Online';  // 默认播放源
                if ($isMagnet) {  // 如果是磁力链接
                    $playSource = 'Magnet';  // 设置磁力播放源
                } elseif ($isEd2k) {  // 如果是电驴链接
                    $playSource = 'Ed2k';  // 设置电驴播放源
                }
                
                // 生成有意义的选集名称
                $episodeName = generateEpisodeName($name, $isMagnet, $isEd2k);  // 生成选集名称
                
                $video = [  // 构建视频信息
                    'vod_id' => 'txt_single_' . md5($filePath) . '_' . $currentLine,  // 视频ID
                    'vod_name' => $name,  // 视频名称
                    'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
                    'vod_remarks' => $isMagnet ? 'Magnet' : ($isEd2k ? 'Ed2k' : 'HD'),  // 视频备注
                    'vod_year' => date('Y'),  // 视频年份
                    'vod_area' => 'China',  // 视频地区
                    'vod_content' => $name . ' - 来自TXT文件的资源',  // 视频内容
                    'vod_play_from' => $playSource,  // 播放来源
                    'vod_play_url' => $episodeName . '$' . $link,  // 播放URL
                    'is_single' => true  // 单资源标志
                ];
            }
            break;  // 跳出循环
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    return $video;  // 返回视频
}

// 生成选集名称的辅助函数
function generateEpisodeName($resourceName, $isMagnet, $isEd2k) {  // 生成选集名称
    // 添加类型图标
    $icon = '';  // 初始化图标
    if ($isMagnet) {  // 如果是磁力链接
        $icon = '🧲';  // 设置磁力图标
    } elseif ($isEd2k) {  // 如果是电驴链接
        $icon = '⚡';  // 设置电驴图标
    } else {  // 普通链接
        $icon = '🌐';  // 设置网络图标
    }
    
    // 简化资源名称（如果太长）
    $displayName = $resourceName;  // 初始化显示名称
    if (strlen($resourceName) > 20) {  // 如果资源名称太长
        $displayName = mb_substr($resourceName, 0, 18, 'UTF-8') . '...';  // 截断名称
    }
    
    return $displayName . ' ' . $icon;  // 返回显示名称和图标
}
// ==================== 第十三部分：M3U查找函数和格式化函数 ====================

// 查找聚合M3U视频详情
function findM3uVideoByLine($filePath, $targetLine) {  // 查找聚合M3U视频
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return null;  // 文件不存在返回null
    }
    
    $handle = @fopen($filePath, 'r');  // 打开文件
    if (!$handle) {  // 如果打开失败
        return null;  // 返回null
    }
    
    $currentLine = 0;  // 初始化当前行号
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    // 读取所有频道
    $allChannels = [];  // 初始化所有频道数组
    $currentName = '';  // 初始化当前名称
    $currentIcon = '';  // 初始化当前图标
    $currentGroup = '';  // 初始化当前分组
    
    while (($line = fgets($handle)) !== false) {  // 逐行读取
        $currentLine++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        if ($line === '') continue;  // 跳过空行
        
        if (strpos($line, '#EXTM3U') === 0) {  // 检查M3U头
            continue;  // 跳过
        }
        
        if (strpos($line, '#EXTINF:') === 0) {  // 检查频道信息行
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
            
            $parts = explode(',', $line, 2);  // 分割频道信息
            if (count($parts) > 1) {  // 如果有名称部分
                $currentName = trim($parts[1]);  // 设置当前名称
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {  // 匹配图标
                $currentIcon = trim($iconMatches[1]);  // 设置当前图标
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {  // 匹配分组
                $currentGroup = trim($groupMatches[1]);  // 设置当前分组
            }
            continue;  // 继续下一行
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];  // 有效协议数组
        $hasValidProtocol = false;  // 初始化有效协议标志
        foreach ($validProtocols as $protocol) {  // 遍历有效协议
            if (stripos($line, $protocol) === 0) {  // 检查协议
                $hasValidProtocol = true;  // 设置有效协议标志
                break;  // 跳出循环
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {  // 如果有有效协议和名称
            $allChannels[] = [  // 添加到频道数组
                'name' => $currentName,  // 频道名称
                'url' => $line,  // 频道URL
                'icon' => $currentIcon,  // 频道图标
                'group' => $currentGroup,  // 频道分组
                'line_number' => $currentLine  // 行号
            ];
            
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    
    if (empty($allChannels)) {  // 如果没有频道
        return null;  // 返回null
    }
    
    // 构建播放列表 - 使用频道名称作为线路名称
    $playUrls = [];  // 初始化播放URL数组
    foreach ($allChannels as $index => $channelData) {  // 遍历所有频道
        $playSource = $channelData['name'];  // 获取播放源名称
        // 如果有分组信息，添加到名称中
        if (!empty($channelData['group'])) {  // 如果有分组
            $playSource = $channelData['name'] . ' [' . $channelData['group'] . ']';  // 添加分组信息
        }
        
        $playUrls[] = $playSource . '$' . $channelData['url'];  // 添加到播放URL数组
    }
    
    $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
    
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);  // 获取文件名
    $imgIndex = 0 % count($defaultImages);  // 计算图片索引
    
    $video = [  // 构建视频信息
        'vod_id' => 'm3u_aggregated_' . md5($filePath),  // 视频ID
        'vod_name' => '[聚合] ' . $fileName,  // 视频名称
        'vod_pic' => $defaultImages[$imgIndex],  // 视频图片
        'vod_remarks' => count($allChannels) . '个频道',  // 视频备注
        'vod_year' => date('Y'),  // 视频年份
        'vod_area' => 'China',  // 视频地区
        'vod_content' => '文件: ' . $fileName . "\n包含 " . count($allChannels) . " 个电视频道\n文件路径: " . $filePath . "\n【聚合模式】所有频道合并到一个项目中",  // 视频内容
        'vod_play_from' => '频道列表',  // 播放来源
        'vod_play_url' => $playUrlStr,  // 播放URL
        'is_aggregated' => true  // 聚合标志
    ];
    
    return $video;  // 返回视频
}

// 查找单资源M3U视频
function findSingleM3uVideoByLine($filePath, $targetLine, $resourceName = '正片') {  // 查找单资源M3U视频
    if (!file_exists($filePath)) {  // 检查文件是否存在
        return null;  // 文件不存在返回null
    }
    
    $handle = @fopen($filePath, 'r');  // 打开文件
    if (!$handle) {  // 如果打开失败
        return null;  // 返回null
    }
    
    $currentLine = 0;  // 初始化当前行号
    $video = null;  // 初始化视频
    $currentName = '';  // 初始化当前名称
    $currentIcon = '';  // 初始化当前图标
    $currentGroup = '';  // 初始化当前分组
    
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    // 从文件路径获取文件名（不含扩展名）作为播放源名称
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);  // 获取文件名
    $playSource = $fileName ?: 'Live';  // 使用文件名作为播放源
    
    $firstLine = fgets($handle);  // 读取第一行
    rewind($handle);  // 重置文件指针
    $hasBOM = (substr($firstLine, 0, 3) == "\xEF\xBB\xBF");  // 检查BOM头
    if ($hasBOM) {  // 如果有BOM头
        fseek($handle, 3);  // 跳过BOM头
    }
    
    while (($line = fgets($handle)) !== false) {  // 逐行读取
        $currentLine++;  // 行号递增
        $line = trim($line);  // 去除首尾空格
        if ($line === '') continue;  // 跳过空行
        
        if (strpos($line, '#EXTM3U') === 0) {  // 检查M3U头
            continue;  // 跳过
        }
        
        if (strpos($line, '#EXTINF:') === 0) {  // 检查频道信息行
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
            
            $parts = explode(',', $line, 2);  // 分割频道信息
            if (count($parts) > 1) {  // 如果有名称部分
                $currentName = trim($parts[1]);  // 设置当前名称
            }
            
            if (preg_match('/tvg-logo="([^"]*)"/i', $line, $iconMatches)) {  // 匹配图标
                $currentIcon = trim($iconMatches[1]);  // 设置当前图标
            }
            
            if (preg_match('/group-title="([^"]*)"/i', $line, $groupMatches)) {  // 匹配分组
                $currentGroup = trim($groupMatches[1]);  // 设置当前分组
            }
            continue;  // 继续下一行
        }
        
        $validProtocols = ['http://', 'https://', 'rtmp://', 'rtsp://', 'udp://', 'magnet:', 'ed2k://'];  // 有效协议数组
        $hasValidProtocol = false;  // 初始化有效协议标志
        foreach ($validProtocols as $protocol) {  // 遍历有效协议
            if (stripos($line, $protocol) === 0) {  // 检查协议
                $hasValidProtocol = true;  // 设置有效协议标志
                break;  // 跳出循环
            }
        }
        
        if ($hasValidProtocol && !empty($currentName)) {  // 如果有有效协议和名称
            if ($currentLine == $targetLine) {  // 检查目标行
                $imgIndex = $currentLine % count($defaultImages);  // 计算图片索引
                
                $videoCover = $currentIcon;  // 获取视频封面
                if (empty($videoCover) || !filter_var($videoCover, FILTER_VALIDATE_URL)) {  // 检查封面是否有效
                    $videoCover = $defaultImages[$imgIndex];  // 使用默认封面
                }
                
                // 选集名称使用资源名称
                $episodeName = $resourceName;  // 设置选集名称
                
                $video = [  // 构建视频信息
                    'vod_id' => 'm3u_single_' . md5($filePath) . '_' . $currentLine,  // 视频ID
                    'vod_name' => $currentName,  // 视频名称
                    'vod_pic' => $videoCover,  // 视频图片
                    'vod_remarks' => 'Live',  // 视频备注
                    'vod_year' => date('Y'),  // 视频年份
                    'vod_area' => 'China',  // 视频地区
                    'vod_content' => $currentName . ' live channel',  // 视频内容
                    'vod_play_from' => $playSource,  // 播放来源
                    'vod_play_url' => $episodeName . '$' . $line,  // 播放URL
                    'is_single' => true  // 单资源标志
                ];
                break;  // 跳出循环
            }
            
            $currentName = '';  // 重置当前名称
            $currentIcon = '';  // 重置当前图标
            $currentGroup = '';  // 重置当前分组
        }
    }
    
    fclose($handle);  // 关闭文件句柄
    return $video;  // 返回视频
}

// 查找分类视频详情
function findCategoryVideoById($videoID) {  // 查找分类视频
    $allFiles = getAllFiles();  // 获取所有文件
    
    foreach ($allFiles as $file) {  // 遍历所有文件
        if (in_array($file['type'], ['db', 'sqlite', 'sqlite3', 'db3'])) {  // 检查数据库文件
            if (!file_exists($file['path']) || !extension_loaded('pdo_sqlite')) {  // 检查文件存在和扩展加载
                continue;  // 继续下一个文件
            }
            
            try {  // 尝试连接数据库
                $db = new PDO("sqlite:" . $file['path']);  // 创建数据库连接
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // 设置错误模式
                
                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);  // 获取所有表名
                
                if (in_array('videos', $tables) && in_array('categories', $tables)) {  // 检查视频和分类表
                    $querySQL = "SELECT v.*, c.name as category_name FROM videos v LEFT JOIN categories c ON v.category_id = c.id WHERE v.id = ?";  // 构建查询SQL
                    $stmt = $db->prepare($querySQL);  // 准备语句
                    $stmt->execute([$videoID]);  // 执行查询
                    $videoData = $stmt->fetch(PDO::FETCH_ASSOC);  // 获取视频数据
                    
                    if ($videoData) {  // 如果找到视频数据
                        $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
                        
                        $playSource = 'Video';  // 初始化播放源
                        $playUrl = $videoData['play_url'] ?? '';  // 提取播放URL
                        
                        if (strpos($playUrl, 'magnet:') === 0) {  // 检查是否为磁力链接
                            $playSource = 'Magnet';  // 设置播放源
                        } elseif (strpos($playUrl, 'ed2k://') === 0) {  // 检查是否为电驴链接
                            $playSource = 'Ed2k';  // 设置播放源
                        }
                        
                        $video = [  // 构建视频信息
                            'vod_id' => 'video_' . $videoData['id'],  // 视频ID
                            'vod_name' => $videoData['name'] ?? 'Unknown Video',  // 视频名称
                            'vod_pic' => $videoData['image'] ?? $defaultImages[0],  // 视频图片
                            'vod_remarks' => $videoData['remarks'] ?? 'HD',  // 视频备注
                            'vod_year' => $videoData['year'] ?? '',  // 视频年份
                            'vod_area' => $videoData['area'] ?? 'China',  // 视频地区
                            'vod_actor' => $videoData['actor'] ?? '',  // 视频演员
                            'vod_director' => $videoData['director'] ?? '',  // 视频导演
                            'vod_content' => $videoData['content'] ?? ($videoData['name'] ?? 'Unknown Video') . ' content',  // 视频内容
                            'vod_play_from' => $playSource . ' · ' . ($videoData['category_name'] ?? 'Unknown Category'),  // 播放来源
                            'vod_play_url' => 'Play$' . $playUrl  // 播放URL
                        ];
                        
                        $db = null;  // 关闭数据库连接
                        return $video;  // 返回视频
                    }
                }
                
                $db = null;  // 关闭数据库连接
            } catch (PDOException $e) {  // 捕获异常
                continue;  // 继续下一个文件
            }
        }
    }
    
    return null;  // 未找到返回null
}

// 查找数据库视频详情
function findDatabaseVideoByIndex($filePath, $tableName, $videoIndex) {  // 查找数据库视频
    if (!file_exists($filePath) || !extension_loaded('pdo_sqlite')) {  // 检查文件存在和扩展加载
        return null;  // 返回null
    }
    
    try {  // 尝试连接数据库
        $db = new PDO("sqlite:" . $filePath);  // 创建数据库连接
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // 设置错误模式
        
        // 首先检查表结构，确定是否有data字段
        $fieldInfo = $db->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);  // 获取表字段信息
        $fieldNames = array_column($fieldInfo, 'name');  // 提取字段名
        
        $hasDataField = in_array('data', $fieldNames);  // 检查是否有data字段
        
        if ($hasDataField) {  // 如果有data字段
            // 如果有data字段，解析JSON数据
            $querySQL = "SELECT data FROM $tableName LIMIT 1 OFFSET " . intval($videoIndex);  // 构建查询SQL
            $stmt = $db->query($querySQL);  // 执行查询
            $jsonData = $stmt->fetch(PDO::FETCH_COLUMN);  // 获取JSON数据
            
            if ($jsonData) {  // 如果有JSON数据
                $videoData = json_decode($jsonData, true);  // 解析JSON数据
                if ($videoData && is_array($videoData)) {  // 检查数据有效性
                    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
                    
                    $videoName = $videoData['title'] ?? $videoData['name'] ?? 'Unknown Video';  // 提取视频名称
                    $videoLink = '';  // 初始化视频链接
                    $playSource = 'Database';  // 初始化播放源
                    
                    // 优先使用磁力链接
                    if (isset($videoData['magnet']) && !empty($videoData['magnet'])) {  // 检查磁力链接
                        $videoLink = $videoData['magnet'];  // 设置视频链接
                        $playSource = 'Magnet';  // 设置播放源
                    } 
                    // 其次使用torrent链接
                    elseif (isset($videoData['torrent']) && !empty($videoData['torrent'])) {  // 检查种子链接
                        $videoLink = $videoData['torrent'];  // 设置视频链接
                        $playSource = 'Torrent';  // 设置播放源
                    }
                    // 最后使用普通链接
                    elseif (isset($videoData['link']) && !empty($videoData['link'])) {  // 检查普通链接
                        $videoLink = $videoData['link'];  // 设置视频链接
                        if (strpos($videoLink, 'magnet:') === 0) {  // 检查是否为磁力链接
                            $playSource = 'Magnet';  // 设置播放源
                        } elseif (strpos($videoLink, 'ed2k://') === 0) {  // 检查是否为电驴链接
                            $playSource = 'Ed2k';  // 设置播放源
                        }
                    }
                    
                    if (empty($videoLink)) {  // 如果没有视频链接
                        $db = null;  // 关闭数据库连接
                        return null;  // 返回null
                    }
                    
                    // 提取其他信息
                    $videoCover = $videoData['image'] ?? $videoData['pic'] ?? $videoData['cover'] ?? $defaultImages[intval($videoIndex) % count($defaultImages)];  // 提取视频封面
                    $videoDesc = $videoData['desc'] ?? $videoData['description'] ?? $videoData['content'] ?? $videoName . ' content';  // 提取视频描述
                    $videoYear = $videoData['year'] ?? '';  // 提取视频年份
                    $videoArea = $videoData['area'] ?? $videoData['region'] ?? 'International';  // 提取视频地区
                    $videoSize = $videoData['size'] ?? '';  // 提取视频大小
                    $uploader = $videoData['uploader'] ?? '';  // 提取上传者
                    
                    // 构建内容描述
                    $content = $videoDesc;  // 初始化内容
                    if (!empty($uploader)) {  // 如果有上传者
                        $content .= "\n上传者: " . $uploader;  // 添加上传者信息
                    }
                    if (!empty($videoSize)) {  // 如果有视频大小
                        $content .= "\n大小: " . $videoSize;  // 添加大小信息
                    }
                    if (isset($videoData['imdb']) && !empty($videoData['imdb'])) {  // 如果有IMDb信息
                        $content .= "\nIMDb: " . $videoData['imdb'];  // 添加IMDb信息
                    }
                    
                    $video = [  // 构建视频信息
                        'vod_id' => 'json_db_' . md5($filePath) . '_' . $tableName . '_' . $videoIndex,  // 视频ID
                        'vod_name' => $videoName,  // 视频名称
                        'vod_pic' => $videoCover,  // 视频封面
                        'vod_remarks' => !empty($videoSize) ? $videoSize : 'HD',  // 视频备注
                        'vod_year' => $videoYear,  // 视频年份
                        'vod_area' => $videoArea,  // 视频地区
                        'vod_content' => $content,  // 视频内容
                        'vod_play_from' => $playSource,  // 播放来源
                        'vod_play_url' => 'Play$' . $videoLink  // 播放URL
                    ];
                    
                    $db = null;  // 关闭数据库连接
                    return $video;  // 返回视频
                }
            }
        } else {  // 没有data字段
            // 如果没有data字段，使用通用查询
            $querySQL = "SELECT * FROM $tableName LIMIT 1 OFFSET " . intval($videoIndex);  // 构建查询SQL
            $stmt = $db->query($querySQL);  // 执行查询
            $rowData = $stmt->fetch(PDO::FETCH_ASSOC);  // 获取行数据
            
            if ($rowData) {  // 如果有行数据
                $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
                
                $videoName = $rowData['name'] ?? $rowData['title'] ?? $rowData['vod_name'] ?? 'Unknown Video';  // 提取视频名称
                $videoUrl = $rowData['magnet'] ?? $rowData['url'] ?? $rowData['link'] ?? $rowData['play_url'] ?? '';  // 提取视频URL
                $playSource = 'Database';  // 初始化播放源
                
                if (strpos($videoUrl, 'magnet:') === 0) {  // 检查是否为磁力链接
                    $playSource = 'Magnet';  // 设置播放源
                } elseif (strpos($videoUrl, 'ed2k://') === 0) {  // 检查是否为电驴链接
                    $playSource = 'Ed2k';  // 设置播放源
                }
                
                if (empty($videoUrl)) {  // 如果没有视频URL
                    $db = null;  // 关闭数据库连接
                    return null;  // 返回null
                }
                
                $videoCover = $rowData['image'] ?? $rowData['pic'] ?? $rowData['cover'] ?? $rowData['vod_pic'] ?? $defaultImages[intval($videoIndex) % count($defaultImages)];  // 提取视频封面
                $videoDesc = $rowData['content'] ?? $rowData['desc'] ?? $rowData['description'] ?? $rowData['vod_content'] ?? $videoName . ' content';  // 提取视频描述
                $videoYear = $rowData['year'] ?? $rowData['vod_year'] ?? date('Y');  // 提取视频年份
                $videoArea = $rowData['area'] ?? $rowData['region'] ?? $rowData['vod_area'] ?? 'China';  // 提取视频地区
                
                $video = [  // 构建视频信息
                    'vod_id' => 'db_' . md5($filePath) . '_' . $tableName . '_' . $videoIndex,  // 视频ID
                    'vod_name' => $videoName,  // 视频名称
                    'vod_pic' => $videoCover,  // 视频封面
                    'vod_remarks' => 'HD',  // 视频备注
                    'vod_year' => $videoYear,  // 视频年份
                    'vod_area' => $videoArea,  // 视频地区
                    'vod_content' => $videoDesc,  // 视频内容
                    'vod_play_from' => $playSource,  // 播放来源
                    'vod_play_url' => 'Play$' . $videoUrl  // 播放URL
                ];
                
                $db = null;  // 关闭数据库连接
                return $video;  // 返回视频
            }
        }
        
        $db = null;  // 关闭数据库连接
        return null;  // 返回null
        
    } catch (PDOException $e) {  // 捕获异常
        return null;  // 返回null
    }
}

// 格式化视频详情
function formatVideoDetail($video) {  // 格式化视频详情
    return [  // 返回格式化视频详情
        'vod_id' => $video['vod_id'] ?? '',  // 视频ID
        'vod_name' => $video['vod_name'] ?? 'Unknown Video',  // 视频名称
        'vod_pic' => $video['vod_pic'] ?? 'https://www.252035.xyz/imgs?t=1335527662',  // 视频图片
        'vod_remarks' => $video['vod_remarks'] ?? 'HD',  // 视频备注
        'vod_year' => $video['vod_year'] ?? '',  // 视频年份
        'vod_area' => $video['vod_area'] ?? 'China',  // 视频地区
        'vod_director' => $video['vod_director'] ?? '',  // 视频导演
        'vod_actor' => $video['vod_actor'] ?? '',  // 视频演员
        'vod_content' => $video['vod_content'] ?? 'Video detail content',  // 视频内容
        'vod_play_from' => $video['vod_play_from'] ?? 'default',  // 播放来源
        'vod_play_url' => $video['vod_play_url'] ?? ''  // 播放URL
    ];
}
// ==================== 第十四部分：媒体聚合项目和文件结束 ====================

// 创建媒体聚合项目
function createMediaAggregatedProjects($videoFiles, $audioFiles) {  // 创建媒体聚合项目
    $aggregatedProjects = [];  // 初始化聚合项目数组
    $defaultImages = ['https://www.252035.xyz/imgs?t=1335527662'];  // 默认图片数组
    
    // 视频聚合项目
    if (!empty($videoFiles)) {  // 如果有视频文件
        $playUrls = [];  // 初始化播放URL数组
        foreach ($videoFiles as $index => $file) {  // 遍历视频文件
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            // 使用文件名作为选集名称，使用原始文件路径
            $playSource = $file['filename'];  // 设置播放源名称
            $playUrls[] = $playSource . '$' . $file['path'];  // 添加到播放URL数组
        }
        
        $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
        
        $aggregatedProjects[] = [  // 添加视频聚合项目
            'vod_id' => 'media_aggregated_video',  // 视频ID
            'vod_name' => '[聚合] 所有视频文件',  // 视频名称
            'vod_pic' => $defaultImages[0],  // 视频图片
            'vod_remarks' => count($videoFiles) . '个视频',  // 视频备注
            'vod_year' => date('Y'),  // 视频年份
            'vod_area' => '本地文件',  // 视频地区
            'vod_content' => '聚合所有视频文件，共 ' . count($videoFiles) . ' 个视频文件',  // 视频内容
            'vod_play_from' => '视频列表',  // 播放来源
            'vod_play_url' => $playUrlStr,  // 播放URL
            'is_aggregated' => true,  // 聚合标志
            'media_type' => 'video_aggregated'  // 媒体类型
        ];
    }
    
    // 音频聚合项目
    if (!empty($audioFiles)) {  // 如果有音频文件
        $playUrls = [];  // 初始化播放URL数组
        foreach ($audioFiles as $index => $file) {  // 遍历音频文件
            $fileSize = file_exists($file['path']) ? formatFileSize(filesize($file['path'])) : '未知大小';  // 获取文件大小
            // 使用文件名作为选集名称，使用原始文件路径
            $playSource = $file['filename'];  // 设置播放源名称
            $playUrls[] = $playSource . '$' . $file['path'];  // 添加到播放URL数组
        }
        
        $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
        
        $aggregatedProjects[] = [  // 添加音频聚合项目
            'vod_id' => 'media_aggregated_audio',  // 视频ID
            'vod_name' => '[聚合] 所有音频文件',  // 视频名称
            'vod_pic' => $defaultImages[1 % count($defaultImages)],  // 视频图片
            'vod_remarks' => count($audioFiles) . '个音频',  // 视频备注
            'vod_year' => date('Y'),  // 视频年份
            'vod_area' => '本地文件',  // 视频地区
            'vod_content' => '聚合所有音频文件，共 ' . count($audioFiles) . ' 个音频文件',  // 视频内容
            'vod_play_from' => '音频列表',  // 播放来源
            'vod_play_url' => $playUrlStr,  // 播放URL
            'is_aggregated' => true,  // 聚合标志
            'media_type' => 'audio_aggregated'  // 媒体类型
        ];
    }
    
    // 全部媒体聚合项目
    if (!empty($videoFiles) && !empty($audioFiles)) {  // 如果有视频和音频文件
        $playUrls = [];  // 初始化播放URL数组
        
        // 添加视频文件
        foreach ($videoFiles as $file) {  // 遍历视频文件
            $playSource = $file['filename'];  // 设置播放源名称
            $playUrls[] = $playSource . '$' . $file['path'];  // 添加到播放URL数组
        }
        
        // 添加音频文件
        foreach ($audioFiles as $file) {  // 遍历音频文件
            $playSource = $file['filename'];  // 设置播放源名称
            $playUrls[] = $playSource . '$' . $file['path'];  // 添加到播放URL数组
        }
        
        $playUrlStr = implode('#', $playUrls);  // 连接播放URL字符串
        
        $aggregatedProjects[] = [  // 添加全部媒体聚合项目
            'vod_id' => 'media_aggregated_all',  // 视频ID
            'vod_name' => '[聚合] 所有媒体文件',  // 视频名称
            'vod_pic' => $defaultImages[2 % count($defaultImages)],  // 视频图片
            'vod_remarks' => (count($videoFiles) + count($audioFiles)) . '个文件',  // 视频备注
            'vod_year' => date('Y'),  // 视频年份
            'vod_area' => '本地文件',  // 视频地区
            'vod_content' => '聚合所有媒体文件，共 ' . count($videoFiles) . ' 个视频 + ' . count($audioFiles) . ' 个音频',  // 视频内容
            'vod_play_from' => '媒体列表',  // 播放来源
            'vod_play_url' => $playUrlStr,  // 播放URL
            'is_aggregated' => true,  // 聚合标志
            'media_type' => 'all_aggregated'  // 媒体类型
        ];
    }
    
    return $aggregatedProjects;  // 返回聚合项目数组
}
?>