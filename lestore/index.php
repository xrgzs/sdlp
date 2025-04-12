<?php
// 定义常量
define('BASE_URL', "https://pc-store.lenovomm.cn/dlservice/getPcSoftDownloadUrlList?");
define('CACHE_TIME', 300);

// 输入参数
$softid = $_GET['softid'] ?? '';

// 参数验证
if (!preg_match('/^[A-Za-z0-9]+$/', $softid)) { // 允许字母和数字组合
    http_response_code(400);
    die('输入参数不合法！');
}

// 生成缓存键
$cacheKey = 'download_url_' . $softid;

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

// 格式化目标 URL
$url = sprintf('%ssoftid=%s', BASE_URL, urlencode($softid));

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
if (json_last_error()!== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['downloadUrls'][0]['downLoadUrl'];

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
