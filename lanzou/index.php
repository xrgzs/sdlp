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
    'url'  => trim(filter_input(INPUT_GET, 'url') ?? ''),
    'pwd'  => trim(strip_tags(filter_input(INPUT_GET, 'pwd') ?? '')),
    'type' => trim(strip_tags($_GET['type'] ?? 'down'))
];

// 支持路径参数 /lanzou/{id}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? preg_replace('/[^a-zA-Z0-9]/', '', trim($pathInfo, '/')) : '';

// 参数校验
if (empty($requestParams['url']) && empty($pathId)) {
    sendErrorResponse('请输入URL或文件ID', 400);
}
// 确保 pwd 不超过 6 位
if (strlen($requestParams['pwd']) > 6) {
    sendErrorResponse('PWD不合法', 400);
}
// 确保 type 只能是 down 或 json
if (!in_array($requestParams['type'], ['down', 'json'])) {
    sendErrorResponse('TYPE不合法', 400);
}

// 如果是路径参数，构建完整URL
if (!empty($pathId)) {
    $requestParams['url'] = 'https://www.lanzoup.com/' . $pathId;
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

// 存储文件信息到缓存（验证数据有效性后再缓存）
if ($isApcuEnabled && !empty($fileInfo['downUrl'])) {
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
    $path = parse_url($url, PHP_URL_PATH);
    if ($path === false || $path === null) {
        sendErrorResponse('非法的蓝奏云链接', 400);
    }
    $path = trim($path, '/');
    return 'https://www.lanzouf.com/' . $path;
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
            '/<span class="p7">文件大小：<\/span>(.*?)<br>/',
            '/<meta name="description" content="文件大小：([^"]+)"\s*\/?>/',
            '/(?:^|>)\s*文件大小：([^<"\n]+)/'
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

    preg_match('/var isngis\s*=\s*\'([^\']+)\'/', $content, $signMatches);
    preg_match('/\/ajaxfile\.php\?file=(\d+)/', $content, $fileIdMatches);

    $postData = [
        'action' => 'downprocess',
        'sign'   => $signMatches[1] ?? '',
        'kd'     => 1,
        'p'      => $password
    ];
    $apiResponse = postRequest($postData, 'https://www.lanzouf.com/ajaxfile.php?file=' . ($fileIdMatches[1] ?? ''), $referer);
    $responseData = json_decode($apiResponse, true);

    if (($responseData['zt'] ?? 0) != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败', 500);
    }

    if (!empty($responseData['inf'])) {
        $fileInfo['name'] = $responseData['inf'];
    }
    if (empty($responseData['dom']) || empty($responseData['url'])) {
        sendErrorResponse('下载链接缺失', 500);
    }

    $landingUrl = $responseData['dom'] . '/file/' . $responseData['url'];
    $fileInfo['downUrl'] = resolveFinalDownloadUrl($landingUrl);
}

/**
 * 处理公开文件
 */
function handlePublicFile(string $content, string $referer, array &$fileInfo): void
{
    if (!preg_match('/<iframe[^>]*src="(\/[^"]+|https:\/\/[^"]+)"/', $content, $iframeMatches)) {
        sendErrorResponse('未找到下载 iframe', 500);
    }
    $iframePath = $iframeMatches[1];
    $iframeUrl = str_starts_with($iframePath, 'http') ? $iframePath : 'https://www.lanzouf.com' . $iframePath;

    $iframeContent = fetchPageContent($iframeUrl, $referer);

    if (preg_match('/id="tourl"[\s\S]*?href="(https:\/\/[^"]+)"/', $iframeContent, $tourlMatches)) {
        $fileInfo['downUrl'] = $tourlMatches[1];
        return;
    }

    preg_match('/\/ajaxfile\.php\?file=(\d+)/', $iframeContent, $fileIdMatches);
    preg_match('/wp_sign\s*=\s*\'([^\']+)\'/', $iframeContent, $signMatches);
    preg_match('/ajaxdata\s*=\s*\'([^\']+)\'/', $iframeContent, $ajaxdataMatches);
    preg_match('/var kdns\s*=\s*(\d+)/', $iframeContent, $kdnsMatches);
    preg_match('/var down_3\s*=\s*\'([^\']*)\'/', $iframeContent, $suffix3Matches);
    preg_match('/var down_1\s*=\s*\'([^\']*)\'/', $iframeContent, $suffix1Matches);

    $postData = [
        'action'     => 'downprocess',
        'websignkey' => $ajaxdataMatches[1] ?? '',
        'signs'      => $ajaxdataMatches[1] ?? '',
        'sign'       => $signMatches[1] ?? '',
        'websign'    => '',
        'kd'         => $kdnsMatches[1] ?? 0,
        'ves'        => 1
    ];

    $apiResponse = postRequest($postData, 'https://www.lanzouf.com/ajaxfile.php?file=' . ($fileIdMatches[1] ?? ''), $iframeUrl);
    $responseData = json_decode($apiResponse, true);

    if (($responseData['zt'] ?? 0) != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败', 500);
    }
    if (empty($responseData['dom']) || empty($responseData['url'])) {
        sendErrorResponse('下载链接缺失', 500);
    }

    $suffix = $suffix3Matches[1] ?? ($suffix1Matches[1] ?? '');
    $landingUrl = $responseData['dom'] . '/file/' . $responseData['url'] . $suffix;
    $fileInfo['downUrl'] = resolveFinalDownloadUrl($landingUrl);
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
 * 执行GET请求（带重试）
 */
function fetchPageContent(string $url, string $referer = '', array $headers = []): string
{
    $ch = curl_init($url);
    $requestHeaders = array_merge([
        'X-FORWARDED-FOR: ' . generateRandomIP(),
        'CLIENT-IP: ' . generateRandomIP()
    ], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => $requestHeaders,
    ]);
    if (!empty($referer)) {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    $maxRetries = 2;
    $retryDelay = 300;
    $response = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        $response = curl_exec($ch);
        if ($response !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    return $response;
}

/**
 * 执行POST请求（带重试）
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
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'X-FORWARDED-FOR: ' . generateRandomIP(),
            'CLIENT-IP: ' . generateRandomIP()
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
    return $response;
}

/**
 * 解析落地页，返回最终 CDN 直链（cookie 经 curl 共享句柄驻留内存，不落盘临时文件）
 */
function resolveFinalDownloadUrl(string $landingUrl): string
{
    $share = curl_share_init();
    curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);

    $commonHeaders = [
        'X-FORWARDED-FOR: ' . generateRandomIP(),
        'CLIENT-IP: ' . generateRandomIP()
    ];

    // 第一次访问落地页，取得 down_ip cookie（存进共享内存）
    fetchEffectiveUrl($landingUrl, $share, $commonHeaders);

    // 第二次带浏览器导航特征头，跟随 302 拿到真实 CDN 直链
    $browserHeaders = array_merge($commonHeaders, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: cross-site',
        'Upgrade-Insecure-Requests: 1',
    ]);
    $finalUrl = fetchEffectiveUrl($landingUrl, $share, $browserHeaders);

    curl_share_close($share);
    return $finalUrl ?: $landingUrl;
}

/**
 * 带共享 cookie 请求并返回最终有效 URL（cookie 全程在内存，不落盘）
 */
function fetchEffectiveUrl(string $url, $share, array $headers): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_COOKIEFILE     => '',   // 启用内存 cookie 引擎，不读写任何文件
        CURLOPT_SHARE          => $share,
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_WRITEFUNCTION  => static function ($ch, $data) {
            return strlen($data);
        },
    ]);
    $maxRetries = 2;
    $retryDelay = 300;
    for ($i = 0; $i <= $maxRetries; $i++) {
        curl_exec($ch);
        if (curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return is_string($effectiveUrl) ? $effectiveUrl : '';
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
