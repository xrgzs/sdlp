<?php
// APCU 缓存配置
$cacheKeyPrefix = '360baoku'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 输入参数
$appId = isset($_GET['appid']) ? $_GET['appid'] : '';

// 参数校验
if (!is_numeric($appId) || strlen($appId) > 10) {
    http_response_code(400);
    die('输入参数不合法！');
}

// 进行更严格的过滤或转义，防止URL注入
$appId = filter_var($appId, FILTER_SANITIZE_NUMBER_INT);

// 生成缓存键
$cacheKey = $cacheKeyPrefix . $appId;

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

// 目标网页 URL
$url = 'https://soft-api.safe.360.cn/main/v1/soft/info?softid=' . $appId;

// 执行 cURL 请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
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
$downloadUrl = $jsonResponse['data']['soft_download'];

// 替换下载地址
$downloadUrl = str_replace('cds.360tpcdn.com', 'cdn-download.soft.360.cn', $downloadUrl);

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && $downloadUrl !== false) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL); // 存储时自动覆盖旧缓存
}

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;
