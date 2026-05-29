<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const CACHE_PREFIX = 'ilanzou_';
const CACHE_TTL = 600;

// 获取请求参数
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '';
$pwd = trim(strip_tags(filter_input(INPUT_GET, 'pwd'))) ?? '';
$type = trim(strip_tags($_GET['type'] ?? 'down'));

// 支持路径参数 /ilanzou/{shareId}
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
// 生成标准 UUID 和加密时间戳
$uuid = generateUuid();
$timestamp = (string)(int)(microtime(true) * 1000);
$ts = aesEncrypt2Hex($timestamp);

// Cookie 文件（跨请求保持 session）
$cookieFile = sys_get_temp_dir() . '/ilanzou_' . md5($uuid) . '.txt';

// 公共查询参数
$commonParams = 'devType=6&devModel=Chrome&uuid=' . urlencode($uuid) . '&extra=2&timestamp=' . $ts;

// API 请求头
$apiHeaders = [
    'Accept: application/json, text/plain, */*',
    'Accept-Encoding: gzip, deflate',
    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Content-Length: 0',
    'DNT: 1',
    'Origin: https://www.ilanzou.com',
    'Pragma: no-cache',
    'Referer: https://www.ilanzou.com/',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
];

// 1. 请求 vip/list（建立 session，获取 cookie）
$vipUrl = 'https://api.ilanzou.com/unproved/buy/vip/list?' . $commonParams;
curlRequest($vipUrl, 'POST', $apiHeaders, $cookieFile);

// 2. 请求 recommend/list 获取文件信息
$recommendParams = $commonParams . '&shareId=' . $shareId . '&type=0&offset=1&limit=60';
if (!empty($pwd)) {
    $recommendParams .= '&code=' . urlencode($pwd);
}
$recommendUrl = 'https://api.ilanzou.com/unproved/recommend/list?' . $recommendParams;
$recommendResponse = curlRequest($recommendUrl, 'POST', $apiHeaders, $cookieFile);
if (empty($recommendResponse['body'])) {
    sendErrorResponse('获取分享信息失败', 500, 'HTTP ' . ($recommendResponse['code'] ?? 0));
}

$recommendData = json_decode($recommendResponse['body'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('分享信息解析失败', 500, $recommendResponse['body']);
}

// 检查上游 API 业务状态码
$upstreamCode = $recommendData['code'] ?? 0;
if ($upstreamCode !== 200 && $upstreamCode !== 0) {
    $upstreamMsg = $recommendData['msg'] ?? '未知错误';
    sendErrorResponse('上游返回错误: ' . $upstreamMsg, 502, $recommendResponse['body']);
}

// 解析文件列表
$list = $recommendData['list'] ?? [];
if (empty($list)) {
    sendErrorResponse('未找到文件或密码错误', 500, $recommendResponse['body']);
}

$fileItem = $list[0] ?? null;
if ($fileItem === null) {
    sendErrorResponse('文件列表为空', 500);
}

// fileIds 和 userId 在 list[0] 级别
$fileIds = $fileItem['fileIds'] ?? '';
$userId = $fileItem['userId'] ?? '';
if (empty($fileIds)) {
    sendErrorResponse('未获取到文件ID', 500);
}

// 文件信息在 fileList[0]
$fileList = $fileItem['fileList'] ?? [];
if (empty($fileList)) {
    sendErrorResponse('文件列表为空', 500);
}

// 检查是否为目录分享
$fileType = $fileList[0]['fileType'] ?? 1;
if ($fileType == 2) {
    sendErrorResponse('该链接为目录分享，暂不支持', 400);
}

$fileId = $fileList[0]['fileId'] ?? $fileIds;
$fileName = $fileList[0]['fileName'] ?? '';
$fileSize = $fileList[0]['fileSize'] ?? 0;
// 3. 生成加密参数获取下载链接
$timestamp2 = (string)(int)(microtime(true) * 1000);
$ts2 = aesEncrypt2Hex($timestamp2);
// downloadId: fileId + "|"（与能跑通的版本一致）
$fidEncode = aesEncrypt2Hex($fileId . '|');
$auth = aesEncrypt2Hex($fileId . '|' . $timestamp2);

$redirectParams = 'downloadId=' . urlencode($fidEncode) . '&enable=1&devType=6&uuid=' . urlencode($uuid) . '&timestamp=' . urlencode($ts2) . '&auth=' . urlencode($auth) . '&shareId=' . $shareId;
$redirectUrl = 'https://api.ilanzou.com/unproved/file/redirect?' . $redirectParams;

// 获取重定向 Location
$redirectResponse = curlRequest($redirectUrl, 'GET', $apiHeaders, $cookieFile);
$downUrl = '';
if (preg_match('/Location:\s*(.+)/i', $redirectResponse['headers'] ?? '', $matches)) {
    $downUrl = trim($matches[1]);
}

// 清理 cookie 文件
@unlink($cookieFile);

if (empty($downUrl)) {
    sendErrorResponse('未获取到下载链接', 500, 'HTTP ' . ($redirectResponse['code'] ?? 0) . ' body: ' . ($redirectResponse['body'] ?? ''));
}

// 格式化文件大小
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

function sendErrorResponse(string $message, int $code = 400, string $upstream = ''): void
{
    http_response_code($code);
    $response = [
        'code' => $code,
        'msg'  => $message
    ];
    if (!empty($upstream)) {
        $response['upstream'] = $upstream;
    }
    die(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function extractShareId(string $url): string
{
    if (preg_match('/^[a-zA-Z0-9]+$/', $url)) {
        return $url;
    }
    if (preg_match('/ilanzou\.com\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return $matches[1];
    }
    return '';
}

function aesEncrypt2Hex(string $source): string
{
    $encrypted = openssl_encrypt($source, 'AES-128-ECB', 'lanZouY-disk-app', OPENSSL_RAW_DATA);
    return bin2hex($encrypted);
}

function generateUuid(): string
{
    return strtolower(sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    ));
}

/**
 * 统一 HTTP 请求函数（带 cookie 持久化）
 */
function curlRequest(string $url, string $method = 'GET', array $headers = [], string $cookieFile = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_ENCODING       => '',
        CURLOPT_PROXY          => '',
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if (!empty($cookieFile)) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    }

    $maxRetries = 2;
    $retryDelay = 300;
    $response = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        $response = curl_exec($ch);
        if ($response !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['code' => 0, 'headers' => '', 'body' => ''];
    }

    return [
        'code'    => $httpCode,
        'headers' => substr($response, 0, $headerSize),
        'body'    => substr($response, $headerSize),
    ];
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
