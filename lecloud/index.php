<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'lecloud_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$pwd = trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '';
$type = trim(strip_tags($_GET['type'] ?? 'down'));

// 支持路径参数 /lecloud/{shareId}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? trim($pathInfo, '/') : '';

// 参数校验
if (empty($url) && empty($pathId)) {
    sendErrorResponse('请输入URL或shareId', 400);
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

// iPhone 风格 User-Agent
$iphoneUA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

// 1. 获取分享信息
$shareInfoUrl = 'https://lecloud.lenovo.com/mshare/api/clouddiskapi/share/public/v1/shareInfo';
$shareInfoBody = json_encode([
    'shareId'    => $shareId,
    'password'   => $pwd,
    'directoryId' => '-1'
]);

$shareInfoHeaders = [
    'Content-Type: application/json',
    'Origin: https://lecloud.lenovo.com',
    'Referer: https://lecloud.lenovo.com',
    'User-Agent: ' . $iphoneUA,
    'X-FORWARDED-FOR: ' . generateRandomIP(),
    'CLIENT-IP: ' . generateRandomIP()
];

$shareInfoResponse = curlPost($shareInfoUrl, $shareInfoBody, $shareInfoHeaders);
if (empty($shareInfoResponse)) {
    sendErrorResponse('获取分享信息失败', 500);
}

$shareInfo = json_decode($shareInfoResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('分享信息解析失败', 500);
}

// 检查是否有文件
if (!isset($shareInfo['result']) || !$shareInfo['result']) {
    $errcode = $shareInfo['errcode'] ?? '未知';
    $errmsg = $shareInfo['errmsg'] ?? '未知错误';
    sendErrorResponse("请求失败: $errcode - $errmsg", 500);
}

$data = $shareInfo['data'] ?? [];
if (!isset($data['passwordVerified']) || !$data['passwordVerified']) {
    sendErrorResponse('密码验证失败', 500);
}

$files = $data['files'] ?? [];
if (empty($files)) {
    sendErrorResponse('未找到文件或密码错误', 500);
}

$fileId = $files[0]['fileId'] ?? '';
$fileName = $files[0]['fileName'] ?? '';
$fileSize = $files[0]['fileSize'] ?? '';

if (empty($fileId)) {
    sendErrorResponse('未获取到文件ID', 500);
}

// 2. 获取打包下载链接
$packageUrl = 'https://lecloud.lenovo.com/mshare/api/clouddiskapi/share/public/v1/packageDownloadWithFileIds';
$browserId = generateUUID();
$packageBody = json_encode([
    'fileIds'   => [$fileId],
    'shareId'   => $shareId,
    'browserId' => $browserId
]);

$packageHeaders = [
    'Content-Type: application/json',
    'Origin: https://lecloud.lenovo.com',
    'Referer: https://lecloud.lenovo.com',
    'User-Agent: ' . $iphoneUA,
    'X-FORWARDED-FOR: ' . generateRandomIP(),
    'CLIENT-IP: ' . generateRandomIP()
];

$packageResponse = curlPost($packageUrl, $packageBody, $packageHeaders);
if (empty($packageResponse)) {
    sendErrorResponse('获取下载链接失败', 500);
}

$packageData = json_decode($packageResponse, true);
$downloadUrl = $packageData['data']['downloadUrl'] ?? '';
if (empty($downloadUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// 3. 跟随重定向获取最终直链
$finalUrl = getRedirectUrl($downloadUrl);
if (empty($finalUrl)) {
    $finalUrl = $downloadUrl;
}

// 格式化文件大小
$readableSize = formatFileSize($fileSize);

// 构建响应
$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $fileName,
    'filesize' => $readableSize,
    'downUrl'  => $finalUrl
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// 存储缓存
if ($isApcuEnabled && !empty($finalUrl)) {
    apcu_store($cacheKey, $result, CACHE_TTL);
}

// 302跳转
if ($type === 'down') {
    header('Location: ' . $finalUrl, true, 302);
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
    if (preg_match('/lecloud\.lenovo\.com\/share\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return $matches[1];
    }
    return '';
}

/**
 * 执行 POST 请求（JSON body）
 */
function curlPost(string $url, string $jsonBody, array $headers = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
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

/**
 * 获取重定向URL
 */
function getRedirectUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP(),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        ]
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

/**
 * 生成 UUID
 */
function generateUUID(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
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
