<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const CACHE_PREFIX = 'guangya_';
const CACHE_TTL = 600;
const API_BASE = 'https://api.guangyapan.com';

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? 'down';

// 支持路径参数 /guangya/{shareId}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? trim($pathInfo, '/') : '';

// 参数校验
if (empty($url) && empty($pathId)) {
    sendErrorResponse('请输入分享链接或shareId', 400);
}
if (!in_array($type, ['down', 'json'])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 提取 shareId
if (!empty($pathId)) {
    $shareId = $pathId;
} else {
    $shareId = extractShareId($url);
}
if (empty($shareId)) {
    sendErrorResponse('URL格式不正确', 400);
}

// 构建缓存 key
$cacheKey = CACHE_PREFIX . md5($shareId);

// 尝试从 APCu 读取缓存
$isApcuEnabled = function_exists('apcu_enabled') && apcu_enabled();
if ($isApcuEnabled) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $cachedData = apcu_fetch($cacheKey);
    if ($cachedData !== false) {
        if ($type === 'down') {
            $cachedJson = json_decode($cachedData, true);
            if (!empty($cachedJson['downUrl'])) {
                header('Location: ' . $cachedJson['downUrl'], true, 302);
                exit;
            }
        }
        echo $cachedData;
        exit;
    }
}

// 公共请求头
$apiHeaders = [
    'Accept: application/json, text/plain, */*',
    'Content-Type: application/json',
    'Origin: https://www.guangyapan.com',
    'Referer: https://www.guangyapan.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
];

// 1. 获取 accessToken
$tokenResp = apiRequest('/userres/v1/get_share_access_token', ['shareId' => $shareId], $apiHeaders);
if (empty($tokenResp) || strtolower($tokenResp['msg'] ?? '') !== 'success') {
    sendErrorResponse('获取访问令牌失败: ' . ($tokenResp['msg'] ?? '未知错误'), 500);
}
$accessToken = $tokenResp['data']['accessToken'] ?? '';
if (empty($accessToken)) {
    sendErrorResponse('未获取到访问令牌', 500);
}

// 2. 获取文件列表
$listResp = apiRequest('/userres/v1/get_share_page_files_list', [
    'shareId'     => $shareId,
    'page'        => 0,
    'pageSize'    => 100,
    'accessToken' => $accessToken
], $apiHeaders);

if (empty($listResp) || strtolower($listResp['msg'] ?? '') !== 'success') {
    sendErrorResponse('获取文件列表失败: ' . ($listResp['msg'] ?? '未知错误'), 500);
}

$files = $listResp['data']['list'] ?? [];
if (empty($files)) {
    sendErrorResponse('分享中没有文件', 500);
}

// 获取第一个文件
$file = $files[0];
$fileId = $file['fileId'] ?? '';
$fileName = $file['fileName'] ?? '';
$fileSize = $file['fileSize'] ?? 0;

if (empty($fileId)) {
    sendErrorResponse('未获取到文件ID', 500);
}

// 3. 获取下载链接
$downloadResp = apiRequest('/userres/v1/get_share_download_url', [
    'shareId'     => $shareId,
    'fileId'      => $fileId,
    'accessToken' => $accessToken
], $apiHeaders);

if (empty($downloadResp) || strtolower($downloadResp['msg'] ?? '') !== 'success') {
    sendErrorResponse('获取下载链接失败: ' . ($downloadResp['msg'] ?? '未知错误'), 500);
}

// 优先使用 signedURL，其次使用 downloadUrl
$downloadUrl = $downloadResp['data']['signedURL'] ?? $downloadResp['data']['downloadUrl'] ?? '';
if (empty($downloadUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// 格式化文件大小
$readableSize = formatFileSize($fileSize);

// 构建响应
$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $fileName,
    'filesize' => $readableSize,
    'downUrl'  => $downloadUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 存储缓存
if ($isApcuEnabled && !empty($downloadUrl)) {
    apcu_store($cacheKey, $result, CACHE_TTL);
}

// 302跳转
if ($type === 'down') {
    header('Location: ' . $downloadUrl, true, 302);
    exit;
}

echo $result;
exit;

/********************** 工具函数 **********************/

/**
 * 发送JSON错误响应
 */
function sendErrorResponse(string $message, int $code = 400): void
{
    http_response_code($code);
    die(json_encode([
        'code' => $code,
        'msg'  => $message
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * 从 URL 提取 shareId
 */
function extractShareId(string $url): string
{
    // 支持 https://www.guangyapan.com/s/{shareId} 格式
    if (preg_match('/guangyapan\.com\/s\/([a-zA-Z0-9_\-]+)/', $url, $matches)) {
        return $matches[1];
    }
    // 支持 share_id=xxx 格式
    if (preg_match('/share_id=([a-zA-Z0-9_\-]+)/', $url, $matches)) {
        return $matches[1];
    }
    return '';
}

/**
 * 执行 API POST 请求（带重试）
 */
function apiRequest(string $path, array $data, array $headers = []): ?array
{
    $url = API_BASE . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers
    ]);

    // 带重试
    $maxRetries = 2;
    $retryDelay = 300;
    $response = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        $response = curl_exec($ch);
        if ($response !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    curl_close($ch);

    if (empty($response)) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * 格式化文件大小
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
