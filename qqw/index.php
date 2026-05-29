<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'qqw_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';

// 参数校验
if (empty($url)) {
    sendErrorResponse('请输入URL', 400);
}

// 构建缓存 key
$cacheKey = CACHE_PREFIX . md5($url);

// 尝试从 APCu 读取缓存
$isApcuEnabled = function_exists('apcu_enabled') && apcu_enabled();
if ($isApcuEnabled) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $cachedData = apcu_fetch($cacheKey);
    if ($cachedData !== false) {
        echo $cachedData;
        exit;
    }
}

// 从 URL 中提取参数
$parsedUrl = parse_url($url);
$queryParams = [];
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $queryParams);
}

$key = $queryParams['k'] ?? '';
$code = $queryParams['code'] ?? '';
$func = $queryParams['func'] ?? '4';

// 如果URL中有key和code，直接拼接下载链接
if (!empty($key) && !empty($code)) {
    $downUrl = 'https://iwx.mail.qq.com/ftn/download?func=' . $func . '&key=' . urlencode($key) . '&code=' . urlencode($code);

    // 尝试获取文件信息（可选，通过请求下载链接的HEAD获取）
    $name = '';
    $filesize = '';

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
    echo $result;
    exit;
}

// 如果没有key和code，尝试请求页面提取变量（QQwTool方式）
$html = curlGet($url);
if (empty($html)) {
    sendErrorResponse('请求分享页面失败', 500);
}

// 用正则提取 JS 变量
preg_match_all('/\s+var\s+(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^;\r\n]*))/', $html, $varMatches, PREG_SET_ORDER);

$vars = [];
foreach ($varMatches as $match) {
    $varName = $match[1];
    $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
    $vars[$varName] = $value;
}

// 提取下载链接
$downUrl = $vars['url'] ?? '';
if (empty($downUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// 替换 \x26 为 &
$downUrl = str_replace('\x26', '&', $downUrl);

// 提取文件信息
$name = $vars['filename'] ?? '';
$filesize = $vars['filesize'] ?? '';

if (empty($name)) {
    sendErrorResponse('未获取到文件名', 500);
}

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

function curlGet(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP(),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
        ]
    ]);
    $maxRetries = 2;
    $retryDelay = 300;
    $response = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        $response = curl_exec($ch);
        if ($response !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    curl_close($ch);
    return $response ?: '';
}

function generateRandomIP(): string
{
    return mt_rand(218, 222) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255);
}
