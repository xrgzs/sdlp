<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36';

// 获取请求参数（使用null合并运算符简化）
$requestParams = [
    'url'  => $_GET['url'] ?? '',
    'pwd'  => $_GET['pwd'] ?? '',
    'type' => $_GET['type'] ?? ''
];

// 参数校验
if (empty($requestParams['url'])) {
    sendErrorResponse('请输入URL', 400);
}

// 构建完整URL
$parsedUrl = parseLanzouUrl($requestParams['url']);
$filePageContent = fetchPageContent($parsedUrl);

// 检查文件有效性
if (strpos($filePageContent, "文件取消分享了") !== false) {
    sendErrorResponse('文件取消分享了', 400);
}

// 提取文件信息
$fileInfo = extractFileInfo($filePageContent);

// 处理带密码链接
if (strpos($filePageContent, "function down_p(){") !== false) {
    handlePasswordProtectedFile($filePageContent, $requestParams['pwd'], $parsedUrl);
} else {
    handlePublicFile($filePageContent, $parsedUrl);
}

// 处理API响应
processApiResponse($fileInfo, $requestParams['type']);

/********************** 工具函数 **********************/

/**
 * 发送JSON错误响应
 */
function sendErrorResponse(string $message, int $code = 400): void
{
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
            $info['name'] = $matches[1];
            break;
        }
    }

    foreach ($patterns['size'] as $pattern) {
        if (preg_match($pattern, $content, $matches)) {
            $info['size'] = $matches[1];
            break;
        }
    }

    return $info;
}

/**
 * 处理带密码文件
 */
function handlePasswordProtectedFile(string $content, string $password, string $referer): void
{
    if (empty($password)) {
        sendErrorResponse('请输入分享密码');
    }

    preg_match_all("/skdklds = '(.*?)';/", $content, $signMatches);
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

    processDownloadUrl($responseData);
}

/**
 * 处理公开文件
 */
function handlePublicFile(string $content, string $referer): void
{
    preg_match_all("/<iframe.*?name=\"[\s\S]*?\"\ssrc=\"\/(.*?)\"/", $content, $iframeMatches);
    $iframeUrl = "https://www.lanzoup.com/" . ($iframeMatches[1][0] ?? '');
    
    $iframeContent = fetchPageContent($iframeUrl);
    preg_match_all("/wp_sign = '(.*?)'/", $iframeContent, $signMatches);
    preg_match_all("/ajaxm\.php\?file=(\d+)/", $iframeContent, $fileIdMatches);

    $postData = [
        "action" => 'downprocess',
        "signs"  => "?ctdf",
		"websignkey" => "jeSg",
		"websignkey" => "",
        "sign"   => $signMatches[1][0] ?? '',
        "kd"     => 1,
        "ves"     => 1
    ];

    $apiResponse = postRequest($postData, "https://www.lanzoup.com/ajaxm.php?file=" . ($fileIdMatches[1][0] ?? ''), $iframeUrl);
    $responseData = json_decode($apiResponse, true);

    if ($responseData['zt'] != 1) {
        sendErrorResponse($responseData['inf'] ?? '解析失败');
    }

    processDownloadUrl($responseData);
}

/**
 * 处理最终下载链接
 */
function processDownloadUrl(array $responseData): void
{
    $primaryUrl = $responseData['dom'] . '/file/' . $responseData['url'];
    $finalUrl = getRedirectUrl($primaryUrl) ?: $primaryUrl;
    $finalUrl = preg_replace('/pid=(.*?.)&/', '', $finalUrl);
    
    $_SESSION['final_download_url'] = $finalUrl; // 存储最终URL供后续使用
}

/**
 * 发送最终响应
 */
function processApiResponse(array $fileInfo, string $requestType): void
{
    if ($requestType === "down") {
        header("Location: " . $_SESSION['final_download_url']);
        exit;
    }

    die(json_encode([
        'code'     => 200,
        'msg'      => '解析成功',
        'name'     => $fileInfo['name'],
        'filesize' => $fileInfo['size'],
        'downUrl'  => $_SESSION['final_download_url']
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
        CURLOPT_HTTPHEADER => [
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
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_REFERER        => $referer,
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
    $headers = get_headers($url, 1);
    return $headers['Location'] ?? '';
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