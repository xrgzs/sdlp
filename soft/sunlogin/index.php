<?php
// Yac 缓存配置
$cacheKeyPrefix = 'sunlogin';
$cacheTTL = 600;

// 获取参数
$name = $_GET['name'] ?? '';
$type = trim(strip_tags($_GET['type'] ?? 'down'));

if (empty($name)) {
    http_response_code(400);
    die('参数 name 不能为空。');
}
if (!in_array($type, ['down', 'json'])) {
    http_response_code(400);
    die('TYPE不合法');
}

// 获取参数 x64
    $args = '';
if (isset($_GET['x64'])) {
    $args = 'x64=1';
}

// 生成缓存键
$cacheKey = $cacheKeyPrefix . $name . (isset($args) ? '_x64' : '');

// 尝试从 Yac 读取缓存
$isYacEnabled = class_exists('Yac');
$yac = $isYacEnabled ? new Yac() : null;
if ($isYacEnabled) {
    $downloadUrl = $yac->get($cacheKey);
    header("X-App-Cache: " . ($downloadUrl !== false ? 'HIT' : 'MISS'));
    if ($downloadUrl !== false) {
        if ($type === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 200, 'msg' => '解析成功', 'downUrl' => $downloadUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        header('Location: ' . $downloadUrl, true, 302);
        exit;
    }
}

// 目标网页 URL 参数
$url = "https://client-api.oray.com/softwares/${name}?${args}";

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_REFERER, "https://sunlogin.oray.com/");

// 发起请求（带重试）
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
$downloadUrl = str_replace('dw.oray.com', 'down.oray.com', $jsonResponse['downloadurl']);

// 将新数据存入 Yac 缓存
if ($isYacEnabled && !empty($downloadUrl)) {
    $yac->set($cacheKey, $downloadUrl, $cacheTTL);
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