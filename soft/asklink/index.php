<?php
// APCU 缓存配置
$cacheKeyPrefix = 'asklink'; // 唯一缓存键名
$cacheTTL = 6 * 3600; // 缓存有效期 6 小时（秒）

// 生成缓存键
$cacheKey = $cacheKeyPrefix . '_win_download_url';

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl !== false) {
        // 命中缓存，直接重定向
        header("Location: $downloadUrl");
        exit;
    }
}

// 缓存不存在或已过期，重新获取数据
$apiUrl = 'https://www.asklink.com/api01/download/list';

// 设置请求头信息
$headers = [
    'Accept: */*',
    'Accept-Encoding: gzip, deflate, br',
    'Accept-Language: zh-CN,zh;q=0.9',
    'Connection: keep-alive',
    'Cookie: i18n_redirected=zh_cn',
    'Referer: https://www.asklink.com/download',
    'Sec-Ch-Ua: "Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
    'Sec-Ch-Ua-Mobile: ?0',
    'Sec-Ch-Ua-Platform: "Windows"',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: same-origin',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
];

// 初始化cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 验证SSL证书
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br'); // 支持压缩

// 执行请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    echo '获取数据失败: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

// 关闭cURL
curl_close($ch);

// 解析JSON响应
$data = json_decode($response, true);

// 检查JSON解析是否成功
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo '解析数据失败: ' . json_last_error_msg();
    exit;
}

// 检查返回状态
if (!isset($data['errcode']) || $data['errcode'] != 0 || !isset($data['data']) || !is_array($data['data'])) {
    http_response_code(500);
    echo '数据格式不正确';
    exit;
}

// 查找clientType为WIN的记录
$downloadUrl = null;
foreach ($data['data'] as $item) {
    if (isset($item['clientType']) && $item['clientType'] === 'WIN' && isset($item['url'])) {
        $downloadUrl = $item['url'];
        break;
    }
}

// 检查是否找到URL
if (empty($downloadUrl)) {
    http_response_code(404);
    echo '未找到WIN对应的下载链接';
    exit;
}

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && $downloadUrl !== false) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL); // 存储时自动覆盖旧缓存
}

// 重定向到下载链接
header('Location: ' . $downloadUrl, true, 302);
exit;
