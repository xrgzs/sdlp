<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0';
const CACHE_PREFIX = 'lanzou_';
const CACHE_TTL = 600;
const LANZOU_BASE_URL = 'https://www.lanzouo.com';
const LANZOU_AJAX_BASE_URL = 'https://apifile.woozooo.com';
const LANZOU_AJAX_FALLBACK_URL = 'https://apifile.lanzouw.com';

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
    $requestParams['url'] = LANZOU_BASE_URL . '/' . $pathId;
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
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    if (
        $parts === false
        || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || !preg_match('/^(?:[a-z0-9-]+\.)*lanzou[a-z]?\.com$/i', $host)
        || empty($parts['path'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
    ) {
        sendErrorResponse('非法的蓝奏云链接', 400);
    }

    return LANZOU_BASE_URL . '/' . trim($parts['path'], '/');
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
    if (empty($fileIdMatches[1])) {
        sendErrorResponse('未找到文件下载信息', 500);
    }

    $postData = [
        'action' => 'downprocess',
        'sign'   => $signMatches[1] ?? '',
        'kd'     => 1,
        'p'      => $password
    ];
    $ajaxPath = '/ajaxfile.php?file=' . $fileIdMatches[1];
    $apiResponse = postRequest(
        $postData,
        LANZOU_AJAX_BASE_URL . $ajaxPath,
        $referer,
        '',
        LANZOU_AJAX_FALLBACK_URL . $ajaxPath
    );
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

    $downloadUrl = buildLanzouDownloadUrl($responseData['dom'], $responseData['url']);
    $fileInfo['downUrl'] = resolveDirectDownloadUrl($downloadUrl, $responseData['dom']);
}

/**
 * 处理公开文件
 */
function handlePublicFile(string $content, string $referer, array &$fileInfo): void
{
    if (!preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $content, $iframeMatches)) {
        sendErrorResponse('未找到下载 iframe', 500);
    }
    $iframePath = $iframeMatches[1];
    $iframeUrl = resolveLanzouUrl($iframePath);

    $iframeContent = fetchPageContent($iframeUrl, $referer);

    if (preg_match('/id=["\']tourl["\'][\s\S]*?href=["\'](https:\/\/[^"\']+)["\']/i', $iframeContent, $tourlMatches)) {
        $landingUrl = html_entity_decode($tourlMatches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $landingParts = parse_url($landingUrl);
        if ($landingParts === false || empty($landingParts['host'])) {
            sendErrorResponse('下载地址无效', 502);
        }
        $landingBase = 'https://' . $landingParts['host'];
        if (isset($landingParts['port'])) {
            $landingBase .= ':' . $landingParts['port'];
        }
        $fileInfo['downUrl'] = resolveDirectDownloadUrl($landingUrl, $landingBase);
        return;
    }

    preg_match('/\/ajaxfile\.php\?file=(\d+)/', $iframeContent, $fileIdMatches);
    preg_match('/wp_sign\s*=\s*\'([^\']+)\'/', $iframeContent, $signMatches);
    preg_match('/ajaxdata\s*=\s*\'([^\']+)\'/', $iframeContent, $ajaxdataMatches);
    preg_match('/var kdns\s*=\s*(\d+)/', $iframeContent, $kdnsMatches);
    preg_match('/var down_3\s*=\s*\'([^\']*)\'/', $iframeContent, $suffix3Matches);
    preg_match('/var down_1\s*=\s*\'([^\']*)\'/', $iframeContent, $suffix1Matches);

    if (empty($fileIdMatches[1])) {
        sendErrorResponse('未找到文件下载信息', 500);
    }

    $postData = [
        'action'     => 'downprocess',
        'websignkey' => $ajaxdataMatches[1] ?? '',
        'signs'      => $ajaxdataMatches[1] ?? '',
        'sign'       => $signMatches[1] ?? '',
        'websign'    => '',
        'kd'         => $kdnsMatches[1] ?? 0,
        'ves'        => 1
    ];

    $ajaxPath = '/ajaxfile.php?file=' . $fileIdMatches[1];
    $apiResponse = postRequest(
        $postData,
        LANZOU_AJAX_BASE_URL . $ajaxPath,
        LANZOU_BASE_URL . '/',
        '',
        LANZOU_AJAX_FALLBACK_URL . $ajaxPath
    );
    $responseData = json_decode($apiResponse, true);

    if (($responseData['zt'] ?? 0) != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败', 500);
    }
    if (empty($responseData['dom']) || empty($responseData['url'])) {
        sendErrorResponse('下载链接缺失', 500);
    }

    $suffix = $suffix3Matches[1] ?? ($suffix1Matches[1] ?? '');
    $downloadUrl = buildLanzouDownloadUrl($responseData['dom'], $responseData['url'] . $suffix);
    $fileInfo['downUrl'] = resolveDirectDownloadUrl($downloadUrl, $responseData['dom']);
}

/**
 * 构建上游返回的下载页地址，并限制目标域名以避免意外请求其他主机
 */
function buildLanzouDownloadUrl(string $domain, string $path): string
{
    $parts = parse_url($domain);
    if (
        $parts === false
        || strtolower($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        sendErrorResponse('下载服务器地址无效', 502);
    }

    $host = strtolower($parts['host']);
    $isLanrar = $host === 'lanrar.com' || str_ends_with($host, '.lanrar.com');
    $isLanzouc = $host === 'lanzouc.com' || str_ends_with($host, '.lanzouc.com');
    if (
        (!$isLanrar && !$isLanzouc)
        || (isset($parts['port']) && !($isLanzouc && (int)$parts['port'] === 661))
    ) {
        sendErrorResponse('下载服务器域名无效', 502);
    }

    $baseUrl = rtrim($domain, '/');
    return $baseUrl . '/file/' . ltrim($path, '/');
}

/**
 * 将 iframe 地址解析为绝对URL
 */
function resolveLanzouUrl(string $url): string
{
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        sendErrorResponse('下载 iframe 地址无效', 500);
    }

    if (isset($parts['host']) && !preg_match('/^(?:[a-z0-9-]+\.)*lanzou[a-z]?\.com$/i', $parts['host'])) {
        sendErrorResponse('下载 iframe 域名无效', 500);
    }

    $path = $parts['path'] ?? '';
    if ($path === '') {
        sendErrorResponse('下载 iframe 地址无效', 500);
    }

    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return LANZOU_BASE_URL . '/' . ltrim($path, '/') . $query;
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
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_COOKIEFILE     => '',
        CURLOPT_SHARE          => getCurlCookieShare(),
    ]);
    if (!empty($referer)) {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    $maxRetries = 2;
    $retryDelay = 300;
    $response = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        configureCurlTimeout($ch);
        $response = curl_exec($ch);
        if ($response !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        sendErrorResponse('蓝奏云请求失败：' . $error, 502);
    }
    $response = retryAfterAcwChallenge($ch, $url, $response);
    curl_close($ch);
    return $response;
}

/**
 * 执行POST请求（带重试）
 */
function postRequest(
    array $data,
    string $url,
    string $referer = '',
    string $cookie = '',
    string $fallbackUrl = ''
) {
    $urls = [$url];
    if ($fallbackUrl !== '' && $fallbackUrl !== $url) {
        $urls[] = $fallbackUrl;
    }
    $lastError = '';

    foreach ($urls as $requestUrl) {
        $ch = curl_init($requestUrl);
        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_REFERER        => $referer,
            CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, text/javascript, */*',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
            ],
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_SHARE          => getCurlCookieShare(),
        ];
        if ($cookie !== '') {
            $options[CURLOPT_COOKIE] = $cookie;
        }
        curl_setopt_array($ch, $options);

        $maxRetries = $fallbackUrl === '' ? 2 : 0;
        $response = false;
        for ($i = 0; $i <= $maxRetries; $i++) {
            configureCurlTimeout($ch);
            $response = curl_exec($ch);
            if ($response !== false && curl_errno($ch) === 0) {
                $response = retryAfterAcwChallenge($ch, $requestUrl, $response, $cookie);
                curl_close($ch);
                return $response;
            }
            $lastError = curl_error($ch);
            if ($i < $maxRetries) {
                usleep(300000);
            }
        }
        curl_close($ch);
    }

    sendErrorResponse('蓝奏云下载接口请求失败：' . $lastError, 502);
}

/**
 * 在请求间共享内存中的蓝奏云验证 cookie
 */
function getCurlCookieShare(): CurlShareHandle
{
    static $share = null;
    if ($share === null) {
        $share = curl_share_init();
        if (!curl_share_setopt($share, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE)) {
            sendErrorResponse('无法初始化蓝奏云 cookie 会话', 500);
        }
    }

    return $share;
}

/**
 * Keep upstream waits within the current API request's time budget
 */
function configureCurlTimeout(CurlHandle $ch, int $maxSeconds = 8): void
{
    $timeout = $maxSeconds;
    curl_setopt_array($ch, [
        CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
        CURLOPT_TIMEOUT        => $timeout,
    ]);
}

/**
 * 计算蓝奏云 WAF 返回的 acw_sc__v2 挑战 cookie
 */
function createAcwScCookie(string $arg1): string
{
    $permutation = [
        15, 35, 29, 24, 33, 16, 1, 38, 10, 9,
        19, 31, 40, 27, 22, 23, 25, 13, 6, 11,
        39, 18, 20, 8, 14, 21, 32, 26, 2, 30,
        7, 4, 17, 5, 3, 28, 34, 37, 12, 36,
    ];
    $key = '3000176000856006061501533003690027800375';
    $arg1 = strtolower($arg1);
    if (strlen($arg1) !== 40 || !ctype_xdigit($arg1)) {
        sendErrorResponse('蓝奏云验证参数无效', 502);
    }

    $shuffled = '';
    foreach ($permutation as $position) {
        $shuffled .= $arg1[$position - 1];
    }

    $cookie = '';
    for ($i = 0; $i < 40; $i += 2) {
        $cookie .= sprintf(
            '%02x',
            hexdec(substr($shuffled, $i, 2)) ^ hexdec(substr($key, $i, 2))
        );
    }

    return $cookie;
}

/**
 * 遇到首次 JavaScript 验证时计算 cookie 并在同一会话重试
 */
function retryAfterAcwChallenge(CurlHandle $ch, string $url, string $response, string $requestCookie = ''): string
{
    if (!preg_match('/<script>\s*var\s+arg1\s*=\s*[\'"]([a-f0-9]{40})[\'"]/i', $response, $matches)) {
        return $response;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        sendErrorResponse('蓝奏云验证域名无效', 502);
    }

    $cookie = createAcwScCookie($matches[1]);
    $expires = gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT';
    $cookieLine = 'Set-Cookie: acw_sc__v2=' . $cookie . '; domain=' . $host . '; path=/; expires=' . $expires;
    if (!curl_setopt($ch, CURLOPT_COOKIELIST, $cookieLine)) {
        sendErrorResponse('无法设置蓝奏云验证 cookie', 502);
    }
    $requestCookie = trim($requestCookie . '; acw_sc__v2=' . $cookie, '; ');
    if (!curl_setopt($ch, CURLOPT_COOKIE, $requestCookie)) {
        sendErrorResponse('无法发送蓝奏云验证 cookie', 502);
    }

    configureCurlTimeout($ch, 16);
    $response = curl_exec($ch);
    if ($response === false || curl_errno($ch) !== 0) {
        sendErrorResponse('蓝奏云验证请求失败：' . curl_error($ch), 502);
    }
    if (preg_match('/<script>\s*var\s+arg1\s*=\s*[\'"]([a-f0-9]{40})[\'"]/i', $response)) {
        sendErrorResponse('蓝奏云验证未通过', 502);
    }

    return $response;
}

/**
 * 不跟随蓝奏下载页跳转，直接返回首个 302 的 CDN 地址
 */
function resolveDirectDownloadUrl(string $downloadUrl, string $baseUrl)
{
    $baseParts = parse_url($baseUrl);
    if (
        $baseParts === false
        || strtolower($baseParts['scheme'] ?? '') !== 'https'
        || empty($baseParts['host'])
    ) {
        sendErrorResponse('下载服务器地址无效', 502);
    }
    $host = strtolower($baseParts['host']);
    $isLanrar = $host === 'lanrar.com' || str_ends_with($host, '.lanrar.com');
    $isLanzouc = $host === 'lanzouc.com' || str_ends_with($host, '.lanzouc.com');
    if (
        (!$isLanrar && !$isLanzouc)
        || (isset($baseParts['port']) && !($isLanzouc && (int)$baseParts['port'] === 661))
    ) {
        sendErrorResponse('下载服务器域名无效', 502);
    }

    $referer = rtrim($baseUrl, '/');
    $challengeCookie = '';

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($downloadUrl);
        $headers = [
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6',
        ];
        $cookie = 'down_ip=1';
        if ($challengeCookie !== '') {
            $cookie .= '; acw_sc__v2=' . $challengeCookie;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_COOKIE         => $cookie,
            CURLOPT_COOKIEFILE     => '',
            CURLOPT_SHARE          => getCurlCookieShare(),
            CURLOPT_USERAGENT      => DEFAULT_USER_AGENT,
            CURLOPT_REFERER        => $referer,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        configureCurlTimeout($ch);
        $body = curl_exec($ch);
        if ($body === false || curl_errno($ch) !== 0) {
            $error = curl_error($ch);
            curl_close($ch);
            sendErrorResponse('获取直链失败：' . $error, 502);
        }

        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseHeaders = substr((string)$body, 0, $headerSize);
        $body = substr((string)$body, $headerSize);
        $location = '';
        if (preg_match_all('/^Location:\s*(.+)$/im', $responseHeaders, $locationMatches)) {
            $location = trim(end($locationMatches[1]));
        }
        curl_close($ch);

        if ($status >= 300 && $status < 400 && $location !== '') {
            return resolveRedirectUrl($downloadUrl, $location);
        }

        if (preg_match('/<script>\s*var\s+arg1\s*=\s*[\'"]([a-f0-9]{40})[\'"]/i', (string)$body, $matches)) {
            $challengeCookie = createAcwScCookie($matches[1]);
            continue;
        }

        return requestSecondaryDownloadUrl((string)$body, $baseUrl);
    }

    sendErrorResponse('下载链接验证失败', 502);
}

/**
 * 解析HTTP重定向地址，但不再向目标地址发起请求
 */
function resolveRedirectUrl(string $requestUrl, string $location): string
{
    if (preg_match('/^https?:\/\//i', $location)) {
        return $location;
    }

    $parts = parse_url($requestUrl);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        sendErrorResponse('下载重定向地址无效', 502);
    }

    if (str_starts_with($location, '//')) {
        return $parts['scheme'] . ':' . $location;
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    if (str_starts_with($location, '/')) {
        return $parts['scheme'] . '://' . $parts['host'] . $port . $location;
    }

    $path = $parts['path'] ?? '/';
    $directory = substr($path, 0, (int)strrpos($path, '/') + 1);
    return $parts['scheme'] . '://' . $parts['host'] . $port . $directory . $location;
}

/**
 * 处理Go实现中的二次下载验证表单
 * 
 * 参考：https://github.com/OpenListTeam/OpenList/blob/main/drivers/lanzou/util.go
 */
function requestSecondaryDownloadUrl(string $html, string $baseUrl)
{
    $params = [];
    if (preg_match_all('/<input\b[^>]*>/i', $html, $inputs)) {
        foreach ($inputs[0] as $input) {
            if (
                preg_match('/\bname\s*=\s*["\']([^"\']+)["\']/i', $input, $name)
                && preg_match('/\bvalue\s*=\s*["\']([^"\']*)["\']/i', $input, $value)
            ) {
                $params[html_entity_decode($name[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')] =
                    html_entity_decode($value[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }

    if (empty($params)) {
        sendErrorResponse('未找到直链或二次验证参数', 502);
    }

    $params['el'] = '2';
    $ajaxUrl = rtrim($baseUrl, '/') . '/ajax.php';
    $referer = rtrim($baseUrl, '/') . '/';

    for ($attempt = 0; $attempt < 2; $attempt++) {
        if ($attempt > 0) {
            usleep(2000000);
        }

        $response = postRequest($params, $ajaxUrl, $referer, 'down_ip=1');
        $data = json_decode($response, true);
        if (is_array($data) && !empty($data['url'])) {
            return (string)$data['url'];
        }
    }

    sendErrorResponse($data['inf'] ?? '获取直链失败', 502);
}
