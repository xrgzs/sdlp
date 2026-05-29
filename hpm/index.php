<?php
// APCU 缓存配置
$cacheKey = 'hpm_list';
$cacheTTL = 600;

$name = trim(strip_tags($_GET['name'] ?? ''));
$type = trim(strip_tags($_GET['type'] ?? 'down'));

// 检查参数
if (empty($name)) {
    http_response_code(400);
    die('未定义必需参数 name !');
}
if (strlen($name) > 100) {
    http_response_code(400);
    die('输入参数过长！');
}
if (!in_array($type, ['down', 'json'])) {
    http_response_code(400);
    die('TYPE不合法');
}

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $jsonResponse = apcu_fetch($cacheKey);
    if ($jsonResponse !== false) {
        // 缓存命中，跳过 API 请求
        goto parse_data;
    }
}

// 发起请求（带重试）
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.hotpe.top/API/HotPE/GetHPMList/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

// 验证数据完整性后再缓存
if (function_exists('apcu_store') && isset($jsonResponse['data'])) {
    apcu_store($cacheKey, $jsonResponse, $cacheTTL);
}

parse_data:

// 遍历 data 数组，获取所有匹配名称的项
$downloadItems = [];
foreach ($jsonResponse['data'] as $sort) {
    foreach ($sort['list'] as $item) {
        if (str_starts_with(($item['name']), $name) !== false) {
            $downloadItems[] = $item;
        }
    }
}

// 自定义比较函数，用于比较两个日期字符串
function compareModified($a, $b) {
    $dateA = strtotime($a['modified']);
    $dateB = strtotime($b['modified']);
    return $dateA - $dateB;
}

// 使用usort和自定义比较函数对数组进行排序
usort($downloadItems, 'compareModified');
// 获取排序后数组的最后一项，即最新日期
$newestItem = end($downloadItems);

// 输出最新的modified的下载地址
if (empty($newestItem['link'])) {
    http_response_code(404);
    die('未找到下载链接。');
}

$downloadUrl = $newestItem['link'];

if ($type === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 200,
        'msg'  => '解析成功',
        'name' => $newestItem['name'] ?? '',
        'downUrl' => $downloadUrl
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Location: ' . $downloadUrl, true, 302);
exit;