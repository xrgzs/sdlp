<?php
// APCU 缓存配置
$cacheKeyPrefix = 'qqsoft'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 输入参数
// $cmdid = isset($_GET['cmdid']) ? $_GET['cmdid'] : ''; // 获取传入的 cmdid 参数
$softid = isset($_GET['softid']) ? $_GET['softid'] : ''; // 获取传入的 req 参数

// 检查参数
// if (!is_numeric($cmdid) || strlen($cmdid) > 10) {
//     die('输入参数不合法！');
// }
if (!is_numeric($softid) || strlen($softid) > 10) {
    http_response_code(400);
    die('输入参数不合法！');
}

// 进一步过滤和转义输入，防止注入
// $cmdid = filter_var($cmdid, FILTER_SANITIZE_NUMBER_INT);
$req = filter_var($softid, FILTER_SANITIZE_NUMBER_INT);

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
    'cmdid' => 3318,
    'jprxReq[req][soft_id_list][]' => $softid,
];

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://luban.m.qq.com/api/public/software-manager/softwareProxy');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

// 发起 POST 请求
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
$downloadUrl = $jsonResponse['resp']['soft_list'][0]['download_url'];

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
