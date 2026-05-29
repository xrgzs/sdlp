<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'qqmail_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? '';

// 参数校验
if (empty($url)) {
    sendErrorResponse('请输入URL', 400);
}
if (!in_array($type, ['down', 'json', ''])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 从 URL 中提取参数
$parsedUrl = parse_url($url);
$queryParams = [];
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);
}

$k = $queryParams['k'] ?? '';
$key = $queryParams['key'] ?? '';
$code = $queryParams['code'] ?? '';

// 构建缓存 key（使用 k 参数或完整 URL 的 md5）
$cacheKey = CACHE_PREFIX . md5(!empty($k) ? $k : $url);

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

// 如果URL中有key和code，直接拼接下载链接
if (!empty($key) && !empty($code)) {
    $downUrl = 'https://wx.mail.qq.com/ftn/download?func=4&key=' . urlencode($key) . '&code=' . urlencode($code);

    $result = json_encode([
        'code'     => 200,
        'msg'      => '解析成功',
        'name'     => '',
        'filesize' => '',
        'downUrl'  => $downUrl
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($isApcuEnabled && !empty($downUrl)) {
        apcu_store($cacheKey, $result, CACHE_TTL);
    }

    // 302跳转
    if ($type === 'down') {
        header('Location: ' . $downUrl, true, 302);
        exit;
    }

    echo $result;
    exit;
}

// 如果没有k参数，无法解析
if (empty($k)) {
    sendErrorResponse('URL格式不正确，缺少k参数', 400);
}

// 调用QQ邮箱API获取文件信息
$apiUrl = 'https://wx.mail.qq.com/s';
$postData = 'f=json&k=' . urlencode($k);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json, text/plain, */*',
        'Referer: ' . $url,
        'User-Agent: ' . DEFAULT_USER_AGENT
    ]
]);

// 发起请求（带重试）
$maxRetries = 2;
$retryDelay = 300;
$response = false;
for ($i = 0; $i <= $maxRetries; $i++) {
    $response = curl_exec($ch);
    if ($response !== false && curl_errno($ch) === 0) break;
    if ($i < $maxRetries) usleep($retryDelay * 1000);
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    sendErrorResponse('获取文件信息失败', 500);
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('解析响应失败', 500);
}

// 检查API返回状态
if (!isset($data['head']['ret']) || $data['head']['ret'] !== 0) {
    $msg = $data['head']['msg'] ?? '未知错误';
    sendErrorResponse('API错误: ' . $msg, 500);
}

$body = $data['body'] ?? [];
if (empty($body)) {
    sendErrorResponse('文件信息为空', 500);
}

// 提取文件信息
$name = $body['name'] ?? '';
$size = $body['size'] ?? 0;
$downUrl = $body['url'] ?? '';

if (empty($name) || empty($downUrl)) {
    sendErrorResponse('未获取到文件信息', 500);
}

// 格式化文件大小
$filesize = formatFileSize($size);

$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $name,
    'filesize' => $filesize,
    'downUrl'  => $downUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($isApcuEnabled && !empty($downUrl)) {
    apcu_store($cacheKey, $result, CACHE_TTL);
}

// 302跳转
if ($type === 'down') {
    header('Location: ' . $downUrl, true, 302);
    exit;
}

echo $result;
exit;

/********************** 工具函数 **********************/

function sendErrorResponse(string $message, int $code = 400): void
{
    http_response_code($code);
    die(json_encode([
        'code' => $code,
        'msg'  => $message
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

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
