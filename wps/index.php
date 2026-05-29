<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'wps_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$pwd = trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '';
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? 'down';

// 支持路径参数 /wps/{shareKey}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? trim($pathInfo, '/') : '';

// 参数校验
if (empty($url) && empty($pathId)) {
    sendErrorResponse('请输入URL或shareKey', 400);
}
if (!in_array($type, ['down', 'json'])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 提取 shareKey
if (!empty($pathId)) {
    $shareKey = $pathId;
} else {
    $shareKey = extractShareKey($url);
}
if (empty($shareKey)) {
    sendErrorResponse('URL格式不正确', 400);
}

// 构建缓存 key
$cacheKey = CACHE_PREFIX . md5($shareKey . $pwd);

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

// 请求下载接口
$apiUrl = 'https://www.kdocs.cn/api/office/file/' . $shareKey . '/download';
$headers = [
    'User-Agent: ' . DEFAULT_USER_AGENT,
    'Referer: https://www.kdocs.cn/l/' . $shareKey,
    'X-FORWARDED-FOR: ' . generateRandomIP(),
    'CLIENT-IP: ' . generateRandomIP()
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
    CURLOPT_HTTPHEADER     => $headers
]);
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
    sendErrorResponse('请求失败', 500);
}

// 解析 JSON 响应
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('响应解析失败', 500);
}

$downloadUrl = $data['download_url'] ?? '';
if (empty($downloadUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// 从 URL 中提取文件名
$name = $data['name'] ?? '';
if (empty($name)) {
    $name = basename(parse_url($downloadUrl, PHP_URL_PATH));
}
$name = urldecode($name);

$filesize = $data['filesize'] ?? '';

// 构建响应
$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $name,
    'filesize' => $filesize,
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
 * 从 URL 提取 shareKey
 */
function extractShareKey(string $url): string
{
    // 支持 https://www.kdocs.cn/l/{shareKey} 格式
    if (preg_match('/kdocs\.cn\/l\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return $matches[1];
    }
    return '';
}

/**
 * 生成随机IP
 */
function generateRandomIP(): string
{
    $ipSegments = [
        mt_rand(218, 222),
        mt_rand(0, 255),
        mt_rand(0, 255),
        mt_rand(0, 255)
    ];
    return implode('.', $ipSegments);
}
