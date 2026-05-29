<?php
// APCU 缓存配置
$cacheKeyPrefix = 'qaxsoft'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 支持路径参数 /qaxsoft/{softid}
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$pathId = !empty($pathInfo) ? trim($pathInfo, '/') : '';

// 输入参数
$softid = $pathId ?: ($_GET['softid'] ?? '');
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? 'down';

// 参数验证
if (empty($softid) || !is_numeric($softid) || strlen($softid) > 10) {
    http_response_code(400);
    die('输入参数不合法！');
}
if (!in_array($type, ['down', 'json'])) {
    http_response_code(400);
    die('TYPE不合法');
}

// 生成缓存键
$cacheKey = $cacheKeyPrefix . $softid;

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl !== false) {
        // 命中缓存，直接重定向并设置响应头
        header("Location: $downloadUrl");
        exit;
    }
}

// 构建请求数据
$data = [
    'type' => 1,
    'soft_id' => $softid,
];

// 生成HuFuToken
function uuid()
{
    $chars = md5(uniqid(mt_rand(), true));
    $uuid = substr($chars, 0, 8) . '-'
        . substr($chars, 8, 4) . '-'
        . substr($chars, 12, 4) . '-'
        . substr($chars, 16, 4) . '-'
        . substr($chars, 20, 12);
    return $uuid;
}
$HuFuToken = uuid();
header("ReqSendID: " . $HuFuToken);

// 发起请求（带重试）
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => 'https://ts.qianxin.com/saas/software/distribution/v1/client/software/download',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => array(
        'User-Agent: Windows apphost 1.0.0.155',
        'Platform-Type: Personal',
        'HuFuToken: ' . $HuFuToken,
        'Content-Type: application/json'
    ),
));
$maxRetries = 2;
$retryDelay = 300;
$response = false;
for ($i = 0; $i <= $maxRetries; $i++) {
    $response = curl_exec($ch);
    if ($response !== false && curl_errno($ch) === 0) break;
    if ($i < $maxRetries) usleep($retryDelay * 1000);
}

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['url'];

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && !empty($downloadUrl)) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL);
}

// 返回响应
if (empty($downloadUrl)) {
    http_response_code(404);
    die('未找到下载链接。');
}

if ($type === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 200,
        'msg'  => '解析成功',
        'downUrl' => $downloadUrl
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Location: ' . $downloadUrl, true, 302);
exit;
