<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const CACHE_PREFIX = 'feijipan_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$pwd = trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '';
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? '';

// 参数校验
if (empty($url)) {
    sendErrorResponse('请输入URL', 400);
}
if (!in_array($type, ['down', 'json', ''])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 提取 shareId
$shareId = extractShareId($url);
if (empty($shareId)) {
    sendErrorResponse('URL格式不正确', 400);
}

// 构建缓存 key
$cacheKey = CACHE_PREFIX . md5($shareId . $pwd);

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

// 生成 uuid 和加密时间戳
$uuid = generateFjUuid(21);
$timestamp = (string)(int)(microtime(true) * 1000);
$ts = aesEncrypt2Hex($timestamp);

// 公共查询参数
$commonParams = 'devType=6&devModel=Chrome&uuid=' . $uuid . '&extra=2&timestamp=' . $ts;

// API 请求头（匹配 Java FjTool.header0）
$apiHeaders = [
    'Accept-Encoding: gzip, deflate',
    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Content-Length: 0',
    'DNT: 1',
    'Pragma: no-cache',
    'Referer: https://www.feijipan.com/',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"'
];

// 1. 请求 vip/list（忽略响应，必须调用）
$vipUrl = 'https://api.feijipan.com/ws/buy/vip/list?' . $commonParams;
curlPost($vipUrl, $apiHeaders);

// 2. 请求 recommend/list 获取文件信息
$recommendParams = $commonParams . '&shareId=' . $shareId . '&type=0&offset=1&limit=60';
if (!empty($pwd)) {
    $recommendParams .= '&code=' . urlencode($pwd);
}
$recommendUrl = 'https://api.feijipan.com/ws/recommend/list?' . $recommendParams;
$recommendResponse = curlPost($recommendUrl, $apiHeaders);
if (empty($recommendResponse)) {
    sendErrorResponse('获取分享信息失败', 500);
}

$recommendData = json_decode($recommendResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('分享信息解析失败', 500);
}

// 解析文件列表
if (empty($recommendData['list'])) {
    sendErrorResponse('未找到文件或密码错误', 500);
}

$fileItem = $recommendData['list'][0] ?? null;
if ($fileItem === null) {
    sendErrorResponse('文件列表为空', 500);
}

// fileIds 在 list[0] 级别
$fileIds = $fileItem['fileIds'] ?? '';
if (empty($fileIds)) {
    sendErrorResponse('未获取到文件ID', 500);
}

// 文件信息在 fileList[0]
$fileList = $fileItem['fileList'] ?? [];
$fileName = !empty($fileList) ? ($fileList[0]['fileName'] ?? '') : '';
$fileSize = !empty($fileList) ? ($fileList[0]['fileSize'] ?? 0) : 0; // KB

// userId 在无认证模式下为 null（与 Java FjTool.userId 一致）
$userId = null;

// 3. 生成加密参数获取下载链接
$timestamp2 = (string)(int)(microtime(true) * 1000);
$ts2 = aesEncrypt2Hex($timestamp2);
$fidEncode = aesEncrypt2Hex($fileIds . '|' . $userId);
$auth = aesEncrypt2Hex($fileIds . '|' . $timestamp2);

$redirectParams = 'downloadId=' . $fidEncode . '&enable=1&devType=6&uuid=' . $uuid . '&timestamp=' . $ts2 . '&auth=' . $auth . '&shareId=' . $shareId;
$redirectUrl = 'https://api.feijipan.com/ws/file/redirect?' . $redirectParams;

// 下载重定向请求头（匹配 Java FjTool.header）
$redirectHeaders = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'DNT: 1',
    'Pragma: no-cache',
    'Referer: https://www.feijix.com/',
    'Sec-Fetch-Dest: document',
    'Sec-Fetch-Mode: navigate',
    'Sec-Fetch-Site: cross-site',
    'Sec-Fetch-User: ?1',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.0.0',
    'sec-ch-ua: "Microsoft Edge";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"'
];

// 获取重定向 Location
$downUrl = getRedirectUrl($redirectUrl, $redirectHeaders);
if (empty($downUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// 格式化文件大小（KB 转可读格式）
$readableSize = formatFileSizeKB($fileSize);

// 构建响应
$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $fileName,
    'filesize' => $readableSize,
    'downUrl'  => $downUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 存储缓存
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

function extractShareId(string $url): string
{
    if (preg_match('/(feijipan\.com|feijix\.com)\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return $matches[2];
    }
    return '';
}

function getAesKey(): string
{
    return 'dingHao-disk-app';
}

function aesEncrypt2Hex(string $source): string
{
    $key = getAesKey();
    $encrypted = openssl_encrypt($source, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    return bin2hex($encrypted);
}

function generateFjUuid(int $length = 21): string
{
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $byte = random_bytes(1);
        $value = ord($byte) & 0x3F;
        if ($value < 36) {
            $result .= base_convert($value, 10, 36);
        } elseif ($value < 62) {
            $result .= strtoupper(base_convert($value - 26, 10, 36));
        } elseif ($value > 62) {
            $result .= '-';
        } else {
            $result .= '_';
        }
    }
    return $result;
}

function curlPost(string $url, array $headers = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
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
    curl_close($ch);
    return $response ?: '';
}

function getRedirectUrl(string $url, array $headers = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers
    ]);
    $maxRetries = 2;
    $retryDelay = 300;
    for ($i = 0; $i <= $maxRetries; $i++) {
        curl_exec($ch);
        if (curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    $info = curl_getinfo($ch);
    curl_close($ch);
    return $info['redirect_url'] ?? '';
}

function formatFileSizeKB(int $kb): string
{
    if ($kb >= 1048576) {
        return round($kb / 1048576, 2) . ' GB';
    } elseif ($kb >= 1024) {
        return round($kb / 1024, 2) . ' MB';
    }
    return $kb . ' KB';
}
