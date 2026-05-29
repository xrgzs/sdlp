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

// 生成 uuid 和加密时间戳
$uuid = generateFjUuid(21);
$timestamp = (string)(int)(microtime(true) * 1000);
$ts = aesEncrypt2Hex($timestamp);

// 公共查询参数
$commonParams = 'devType=6&devModel=Chrome&uuid=' . $uuid . '&extra=2&timestamp=' . $ts;

// API 请求头（匹配 Java IzTool）
$apiHeaders = [
    'Accept: application/json, text/plain, */*',
    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
    'Cache-Control: no-cache',
    'Connection: keep-alive',
    'Content-Length: 0',
    'DNT: 1',
    'Host: api.ilanzou.com',
    'Origin: https://www.ilanzou.com/',
    'Pragma: no-cache',
    'Referer: https://www.ilanzou.com/',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-site',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"'
];

// 1. 请求 vip/list（忽略响应，必须调用）
$vipUrl = 'https://api.ilanzou.com/unproved/buy/vip/list?' . $commonParams;
curlPost($vipUrl, $apiHeaders);

// 2. 请求 recommend/list 获取文件信息
$recommendParams = $commonParams . '&shareId=' . $shareId . '&type=0&offset=1&limit=60';
if (!empty($pwd)) {
    $recommendParams .= '&code=' . urlencode($pwd);
}
$recommendUrl = 'https://api.ilanzou.com/unproved/recommend/list?' . $recommendParams;
$recommendResponse = curlPost($recommendUrl, $apiHeaders);
if (empty($recommendResponse)) {
    sendErrorResponse('获取分享信息失败', 500, 'empty response');
}

// 检查是否需要 acw_sc__v2 反爬
$acwCookie = '';
if (strpos($recommendResponse, "var arg1='") !== false) {
    if (preg_match("/var\s+arg1\s*=\s*'([^']+)'/", $recommendResponse, $arg1Match)) {
        $arg1 = $arg1Match[1];
        $acwCookie = acwScV2Simple($arg1);
    }
    // 带上 cookie 重新请求
    $cookieHeaders = $apiHeaders;
    $cookieHeaders[] = 'Cookie: acw_sc__v2=' . $acwCookie;
    $recommendResponse = curlPost($recommendUrl, $cookieHeaders);
    if (empty($recommendResponse)) {
        sendErrorResponse('获取分享信息失败(反爬重试)', 500, 'empty response after acw retry');
    }
}

$recommendData = json_decode($recommendResponse, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendErrorResponse('分享信息解析失败', 500, $recommendResponse);
}

// 检查上游 API 业务状态码
$upstreamCode = $recommendData['code'] ?? 0;
if ($upstreamCode !== 200 && $upstreamCode !== 0) {
    $upstreamMsg = $recommendData['msg'] ?? '未知错误';
    sendErrorResponse('上游返回错误: ' . $upstreamMsg, 502, $recommendResponse);
}

// 解析文件列表
$list = $recommendData['list'] ?? [];
if (empty($list)) {
    sendErrorResponse('未找到文件或密码错误', 500, $recommendResponse);
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

$fileName = $fileList[0]['fileName'] ?? '';
$fileSize = $fileList[0]['fileSize'] ?? 0; // KB

// 3. 生成加密参数获取下载链接
$timestamp2 = (string)(int)(microtime(true) * 1000);
$ts2 = aesEncrypt2Hex($timestamp2);
$fidEncode = aesEncrypt2Hex($fileIds . '|' . $userId);
$auth = aesEncrypt2Hex($fileIds . '|' . $timestamp2);

$redirectParams = 'downloadId=' . $fidEncode . '&enable=1&devType=6&uuid=' . $uuid . '&timestamp=' . $ts2 . '&auth=' . $auth . '&shareId=' . $shareId;
$redirectUrl = 'https://api.ilanzou.com/unproved/file/redirect?' . $redirectParams;

// 下载重定向请求头
$redirectHeaders = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
    'Referer: https://www.ilanzou.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.0.0',
    'sec-ch-ua: "Microsoft Edge";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"'
];
if (!empty($acwCookie)) {
    $redirectHeaders[] = 'Cookie: acw_sc__v2=' . $acwCookie;
}

// 获取重定向 Location
$downUrl = getRedirectUrl($redirectUrl, $redirectHeaders);
if (empty($downUrl)) {
    sendErrorResponse('未获取到下载链接', 500, 'redirect url: ' . $redirectUrl);
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
    if (preg_match('/ilanzou\.com\/s\/([a-zA-Z0-9]+)/', $url, $matches)) {
        return $matches[1];
    }
    return '';
}

function getAesKey(): string
{
    return 'lanZouY-disk-app';
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

function acwScV2Simple(string $arg1): string
{
    $posList = [15,35,29,24,33,16,1,38,10,9,19,31,40,27,22,23,25,13,6,11,39,18,20,8,14,21,32,26,2,30,7,4,17,5,3,28,34,37,12,36];
    $mask = "3000176000856006061501533003690027800375";
    $outPutList = array_fill(0, 40, '');

    for ($i = 0; $i < strlen($arg1); $i++) {
        $ch = $arg1[$i];
        for ($j = 0; $j < count($posList); $j++) {
            if ($posList[$j] == $i + 1) {
                $outPutList[$j] = $ch;
            }
        }
    }

    $arg2 = implode('', $outPutList);
    $result = '';
    $length = min(strlen($arg2), strlen($mask));

    for ($i = 0; $i < $length; $i += 2) {
        $strVal = hexdec(substr($arg2, $i, 2));
        $maskVal = hexdec(substr($mask, $i, 2));
        $xor = $strVal ^ $maskVal;
        $result .= sprintf('%02x', $xor);
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
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
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
