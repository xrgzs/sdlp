<?php
// APCU 缓存配置
$cacheKeyPrefix = 'scoop'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 定义常量
define('BASE_URL', "https://pc-store.lenovomm.cn/dlservice/getPcSoftDownloadUrlList?");

// 输入参数
$softid = $_GET['softid'] ?? '';

// 参数验证
if (!preg_match('/^[A-Za-z0-9]+$/', $softid)) { // 允许字母和数字组合
    http_response_code(400);
    die('输入参数不合法！');
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

// 格式化目标 URL
$url = sprintf('%ssoftid=%s', 'https://pc-store.lenovomm.cn/dlservice/getPcSoftDownloadUrlList?', urlencode($softid));

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 发起 GET 请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['downloadUrls'][0]['downLoadUrl'];

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
