<?php
// APCU 缓存配置
$cacheKey = 'alist_wallpaper'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: ". (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $response = apcu_fetch($cacheKey);
    if ($response !== false) {
        // 缓存命中，跳过 API 请求
        goto parse_json; // 直接进入解析阶段
    }
}

// 初始化 cURL
$ch = curl_init();

curl_setopt_array($ch, array(
   CURLOPT_URL => 'https://alist.xrgzs.top/api/fs/list',
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_CUSTOMREQUEST => 'POST',
   CURLOPT_POSTFIELDS =>'{
    "path":"/图片/walls",
    "password": "",
    "page": 1,
    "per_page": 0,
    "refresh": false
}',
   CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
   ),
));

// 发起请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && $response !== false) {
    apcu_store($cacheKey, $response, $cacheTTL); // 存储时自动覆盖旧缓存
}

parse_json:
// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取数组长度
$length = count($jsonResponse['data']['content']);

// 根据长度生成一个随机索引
$random_index = mt_rand(0, $length - 1);

// 获取随机下载地址
$downloadUrl = $jsonResponse['data']['content'][$random_index]['thumb'];

// 替换为高清图
$downloadUrl = str_replace('width=176&height=176', 'width=1920&height=1080&outputFormat=jpg', $downloadUrl);

if (!empty($downloadUrl)) {
    // 跳转到下载地址
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;