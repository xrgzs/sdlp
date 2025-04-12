<?php
// 定义常量或配置项
define('BASE_URL', 'https://soft-api.safe.360.cn/main/v1/soft/info?softid=');
define('CACHE_TIME', 300);

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
$cacheKey = 'download_url_' . $appId;

// 检查APCU扩展是否加载
if (extension_loaded('apcu')) {
    // 尝试从APCU缓存中获取下载链接
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl!== false) {
        // 命中缓存，直接重定向并设置响应头
        header('cache-method: APCU,hit');
        header("Location: $downloadUrl");
        exit;
    } else {
        // 未命中缓存，设置响应头表示正在写入缓存
        header('cache-method: APCU,updating');
    }
} else {
    // APCU扩展未加载，不使用缓存，设置响应头
    header('cache-method: none');
}

// 目标网页 URL
$url = BASE_URL . $appId;

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
if (json_last_error()!== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['soft_download'];

// 替换下载地址
$downloadUrl = str_replace('cds.360tpcdn.com', 'cdn-download.soft.360.cn', $downloadUrl);

// 如果使用了APCU扩展，将下载链接存入缓存
if (extension_loaded('apcu')) {
    apcu_store($cacheKey, $downloadUrl, CACHE_TIME);
}

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;
