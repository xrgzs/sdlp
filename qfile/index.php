<?php

// 初始化响应头
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 定义常量
const CACHE_PREFIX = 'qfile_';
const CACHE_TTL = 600;

// 获取请求参数
$requestParams = [
    // 'url'  => filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? '',
    'batchId'  => trim(strip_tags(filter_input(INPUT_GET, 'batchId'))) ?? '',
    'type' => trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? ''
];

// 参数校验
// if (empty($requestParams['url'])) {
//     sendErrorResponse('请输入URL', 400);
// }

// 确保 type 只能是 down, json 或空
if (!in_array($requestParams['type'], ['down', 'json', ''])) {
    sendErrorResponse('TYPE不合法', 400);
}

// apcu_clear_cache();
// 构建完整URL
// $parsedUrl = parseQFileUrl($requestParams['url']);

$cacheKey = CACHE_PREFIX . md5(json_encode($requestParams));

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

// TODO: 提取batchId


// 获取下载链接
if (empty($requestParams['batchId'])) {
    sendErrorResponse('请输入batchId', 400);
}
$link = getLink($requestParams['batchId']);
if (empty($link)) {
    sendErrorResponse('获取下载链接失败', 500);
}
processApiResponse($link, $requestParams['type']);
exit;

/********************** API函数 **********************/

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
 * 发送最终响应
 */
function processApiResponse(string $url, string $requestType): void
{
    if ($requestType === "down") {
        header("Location: " .  $url);
        exit;
    }

    die(json_encode([
        'code'     => 200,
        'msg'      => '解析成功',
        'url'     => $url
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/********************** 工具函数 **********************/


/**
 * 获取下载链接
 */
function getLink(string $batchId): string
{
    $body = [
        'req_head' => ['agent' => 8],
        'download_info' => [
            [
                'batch_id' => $batchId,
                'scene' => [
                    'business_type' => 4,
                    'app_type' => 22,
                    'scene_type' => 5
                ],
                'index_node' => [
                    'file_uuid' => $batchId
                ],
                'url_type' => 2,
                'download_scene' => 0
            ]
        ],
        'scene_type' => 103
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://qfile.qq.com/http2rpc/gotrpc/noauth/trpc.qqntv2.richmedia.InnerProxy/BatchDownload',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_COOKIE => 'uin=9000002; p_uin=9000002',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0',
            'Accept: application/json',
            'Content-Type: application/json',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Origin: https://qfile.qq.com',
            'Referer: https://qfile.qq.com', // 'https://qfile.qq.com/q/xxxxxx'
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'sec-ch-ua: "Not(A:Brand";v="8", "Chromium";v="144", "Microsoft Edge";v="144"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'x-oidb: {"uint32_command":"0x9248", "uint32_service_type":"4"}',
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    if (PHP_VERSION_ID < 80500) {
        curl_close($curl);
    }
    if ($err) {
        sendErrorResponse('cURL 请求出错：' . $err, 500);
        return '';
    }
    $jsonResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('JSON 解析失败: ' . json_last_error_msg(), 500);
        return '';
    }
    if (isset($jsonResponse['data']['download_rsp'][0]['url'])) {
        return $jsonResponse['data']['download_rsp'][0]['url'];
    } else {
        sendErrorResponse('获取下载链接失败', 500);
        return '';
    }
}
