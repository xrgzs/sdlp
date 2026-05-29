<?php
// APCU 缓存配置
$cacheKey = 'itab_wallpaper'; // 唯一缓存键名
$cacheTTL = 300; // 缓存有效期 5 分钟（秒）

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl !== false) {
        header("Location: $downloadUrl");
        exit;
    }
}

// 定义分类数组
$sorts = ['nature','art','architecture','geometry'];
// no: acg

// 随机选择一个分类
$sort = $sorts[array_rand($sorts)];

// 加载分类到请求头
header('Sort: ' . $sort);

// 目标网页 URL
$url = "https://api.codelife.cc/wallpaper/random?lang=cn&category=$sort";

// 初始化 cURL
$headers = array(
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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
// $downloadUrl = $jsonResponse['data'];

// 使用parse_url解析URL
$parsedUrl = parse_url($jsonResponse['data']);

// 重新组合URL，排除查询部分
$downloadUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'];

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && !empty($downloadUrl)) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL);
}

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;