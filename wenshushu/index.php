<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'wenshushu_';
const CACHE_TTL = 600;

// Android 风格 User-Agent
const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$pwd = trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '';
$type = trim(strip_tags($_GET['type'] ?? 'down'));

// 支持路径参数 /wenshushu/{tid}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? preg_replace('/[^a-zA-Z0-9]/', '', trim($pathInfo, '/')) : '';

// 参数校验
if (empty($url) && empty($pathId)) {
    sendErrorResponse('请输入URL或tid', 400);
}
if (!in_array($type, ['down', 'json'])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 提取 tid
if (!empty($pathId)) {
    $tid = $pathId;
} else {
    $tid = extractTid($url);
}
if (empty($tid)) {
    sendErrorResponse('URL格式不正确', 400);
}

// 构建缓存 key
$cacheKey = CACHE_PREFIX . md5($tid . $pwd);

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
$commonHeaders = [
    'Content-Type: application/json',
    'User-Agent: ' . ANDROID_UA,
    'sec-ch-ua-platform: Android',
    'X-FORWARDED-FOR: ' . generateRandomIP(),
    'CLIENT-IP: ' . generateRandomIP()
];

// 1. 匿名登录获取 token
$loginUrl = 'https://www.wenshushu.cn/ap/login/anonymous';
$loginBody = json_encode(['dev_info' => '{}']);
$loginResponse = curlPost($loginUrl, $loginBody, $commonHeaders);
if (empty($loginResponse)) {
    sendErrorResponse('匿名登录失败', 500);
}

$loginData = json_decode($loginResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('登录响应解析失败', 500);
}

$token = $loginData['data']['token'] ?? '';
if (empty($token)) {
    sendErrorResponse('未获取到token', 500);
}

// 设置 X-Token 请求头
$authHeaders = array_merge($commonHeaders, ['X-Token: ' . $token]);

// 2. 获取分享任务信息
$taskUrl = 'https://www.wenshushu.cn/ap/task/mgrtask';
$taskBody = json_encode([
    'tid'      => $tid,
    'password' => $pwd
]);
$taskResponse = curlPost($taskUrl, $taskBody, $authHeaders);
if (empty($taskResponse)) {
    sendErrorResponse('获取任务信息失败', 500);
}

$taskData = json_decode($taskResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('任务信息解析失败', 500);
}

$ufileid = $taskData['data']['ufileid'] ?? '';
$boxid = $taskData['data']['boxid'] ?? '';
if (empty($ufileid) || empty($boxid)) {
    sendErrorResponse('未获取到文件信息或密码错误', 500);
}

// 3. 获取文件列表
$listUrl = 'https://www.wenshushu.cn/ap/ufile/list';
$listBody = json_encode([
    'start' => 0,
    'bid'   => $boxid,
    'pid'   => $ufileid,
    'type'  => 1,
    'size'  => 50
]);
$listResponse = curlPost($listUrl, $listBody, $authHeaders);
if (empty($listResponse)) {
    sendErrorResponse('获取文件列表失败', 500);
}

$listData = json_decode($listResponse, true);
$fileList = $listData['data']['fileList'] ?? [];
if (empty($fileList)) {
    sendErrorResponse('文件列表为空', 500);
}

$fname = $fileList[0]['fname'] ?? '';
$fid = $fileList[0]['fid'] ?? '';
if (empty($fid)) {
    sendErrorResponse('未获取到文件ID', 500);
}

// 4. 获取下载签名
$signUrl = 'https://www.wenshushu.cn/ap/dl/sign';
$signBody = json_encode([
    'consumeCode' => 0,
    'type'        => 1,
    'ufileid'     => $fid
]);
$signResponse = curlPost($signUrl, $signBody, $authHeaders);
if (empty($signResponse)) {
    sendErrorResponse('获取下载签名失败', 500);
}

$signData = json_decode($signResponse, true);
$downUrl = $signData['data']['url'] ?? '';
if (empty($downUrl)) {
    sendErrorResponse('未获取到下载链接', 500);
}

// URL 解码
$downUrl = urldecode($downUrl);

// 构建响应
$result = json_encode([
    'code'     => 200,
    'msg'      => '解析成功',
    'name'     => $fname,
    'filesize' => '',
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
 * 从 URL 提取 tid
 */
function extractTid(string $url): string
{
    if (preg_match('/wenshushu\.cn\/f\/([a-zA-Z0-9]+)/', $url, $matches)) {
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
