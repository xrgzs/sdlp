<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'lanzou_';
const CACHE_TTL = 600;

// 获取请求参数
$requestParams = [
    'url'  => filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '',
    'pwd'  => trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '',
    'type' => trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? ''
];

// 参数校验
if (empty($requestParams['url'])) {
    sendErrorResponse('请输入URL', 400);
}
// 确保 pwd 不超过 6 位
if (strlen($requestParams['pwd']) > 6) {
    sendErrorResponse('PWD不合法', 400);
}
// 确保 type 只能是 down, json 或空
if (!in_array($requestParams['type'], ['down', 'json', ''])) {
    sendErrorResponse('TYPE不合法', 400);
}

// apcu_clear_cache();
// 构建完整URL
$parsedUrl = parseLanzouUrl($requestParams['url']);

$cacheKey = CACHE_PREFIX . md5($parsedUrl . $requestParams['pwd']);

// 尝试从 APCu 读取缓存
$isApcuEnabled = function_exists('apcu_enabled') && apcu_enabled();
if ($isApcuEnabled) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $cachedData = apcu_fetch($cacheKey);
    if ($cachedData !== false) {
        // 缓存命中，跳过 API 请求
        processApiResponse($cachedData, $requestParams['type']);
        exit;
    }
}

// 1. 获取网页内容
$filePageContent = fetchPageContent($parsedUrl);

// 2. 检查文件有效性
if (strpos($filePageContent, "文件取消分享了") !== false) {
    sendErrorResponse('文件取消分享了', 400);
}

// 3. 提取文件信息
$fileInfo = extractFileInfo($filePageContent);

// 4. 解析带密码/公开链接的直链
if (strpos($filePageContent, "function down_p(){") !== false) {
    handlePasswordProtectedFile($filePageContent, $requestParams['pwd'], $parsedUrl, $fileInfo);
} else {
    handlePublicFile($filePageContent, $parsedUrl, $fileInfo);
}

// 存储文件信息到缓存（如果APCu可用）
if ($isApcuEnabled) {
    apcu_store($cacheKey, $fileInfo, CACHE_TTL);
}
// 处理API响应
processApiResponse($fileInfo, $requestParams['type']);
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
 * 构建完整蓝奏云URL
 */
function parseLanzouUrl(string $url): string
{
    $path = explode('.com/', $url)[1] ?? '';
    return 'https://www.lanzoup.com/' . $path;
}

/**
 * 提取文件信息（名称、大小）
 */
function extractFileInfo(string $content): array
{
    $patterns = [
        'name' => [
            '/style="font-size: 30px;text-align: center;padding: 56px 0px 20px 0px;">(.*?)<\/div>/',
            '/<div class="n_box_3fn".*?>(.*?)<\/div>/',
            '/var filename = \'(.*?)\';/',
            '/div class="b"><span>(.*?)<\/span><\/div>/'
        ],
        'size' => [
            '/<div class="n_filesize".*?>大小：(.*?)<\/div>/',
            '/<span class="p7">文件大小：<\/span>(.*?)<br>/'
        ]
    ];

    $info = ['name' => '', 'size' => ''];

    foreach ($patterns['name'] as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $info['name'] = htmlspecialchars($matches[1]);
            break;
        }
    }

    foreach ($patterns['size'] as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $info['size'] = htmlspecialchars($matches[1]);
            break;
        }
    }

    return $info;
}

/**
 * 处理带密码文件
 */
function handlePasswordProtectedFile(string $content, string $password, string $referer, array &$fileInfo): void
{
    if (empty($password)) {
        sendErrorResponse('请输入分享密码');
    }

    preg_match_all("/bcdf = '(.*?)';/", $content, $signMatches);
    preg_match_all("/ajaxm\.php\?file=(\d+)/", $content, $fileIdMatches);

    $postData = [
        "action" => 'downprocess',
        "sign"   => $signMatches[1][0] ?? '',
        "p"      => $password,
        "kd"     => 1
    ];

    $apiResponse = postRequest($postData, "https://www.lanzoup.com/ajaxm.php?file=" . ($fileIdMatches[1][0] ?? ''), $referer);
    $responseData = json_decode($apiResponse, true);

    if ($responseData['zt'] != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败');
    }

    $fileInfo['downUrl'] = processDownloadUrl($responseData);
}

/**
 * 处理公开文件
 */
function handlePublicFile(string $content, string $referer, array &$fileInfo): void
{
    preg_match_all("/<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"/", $content, $iframeMatches);
    $iframeUrl = "https://www.lanzoup.com/" . ($iframeMatches[1][0] ?? '');

    $iframeContent = fetchPageContent($iframeUrl);
    preg_match_all("/wp_sign = '(.*?)';/", $iframeContent, $signMatches);
    preg_match_all("/ajaxm\.php\?file=(\d+)/", $iframeContent, $fileIdMatches);

    $postData = [
        "action" => 'downprocess',
        "sign"   => $signMatches[1][0] ?? '',
        "kd"     => 1,
        "ves"    => 1
    ];

    $apiResponse = postRequest($postData, "https://www.lanzoup.com/ajaxm.php?file=" . ($fileIdMatches[1][0] ?? ''), $iframeUrl);
    $responseData = json_decode($apiResponse, true);

    if ($responseData['zt'] != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败', 500);
    }

    $fileInfo['downUrl'] = processDownloadUrl($responseData);
}

/**
 * 处理最终下载链接
 */
function processDownloadUrl(array $responseData): string
{
    $primaryUrl = $responseData['dom'] . '/file/' . $responseData['url'];
    $finalUrl = getRedirectUrl($primaryUrl) ?: $primaryUrl;
    $finalUrl = preg_replace('/pid=(.*?.)&/', '', $finalUrl);
    return $finalUrl;
}

/**
 * 发送最终响应
 */
function processApiResponse(array $fileInfo, string $requestType): void
{
    if ($requestType === "down") {
        header("Location: " . $fileInfo['downUrl']);
        exit;
    }

    die(json_encode([
        'code'     => 200,
        'msg'      => '解析成功',
        'name'     => $fileInfo['name'],
        'filesize' => $fileInfo['size'],
        'downUrl'  => $fileInfo['downUrl']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/********************** 网络请求相关 **********************/

/**
 * 执行GET请求
 */
function fetchPageContent(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP()
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * 执行POST请求
 */
function postRequest(array $data, string $url, string $referer = ''): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_REFERER        => $referer,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP()
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * 获取重定向URL
 */
function getRedirectUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HEADER           => true,
        CURLOPT_NOBODY     => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP(),
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-encoding: gzip, deflate, br, zstd',
            'accept-language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
            'priority: u=0, i',
            'upgrade-insecure-requests: 1',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7'
        ]
    ]);
    curl_exec($ch);
    $url=curl_getinfo($ch);
    curl_close($ch);
    return $url["redirect_url"];
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