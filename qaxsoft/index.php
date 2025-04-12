<?php

// 定义接口
$BASE_URL = "https://ts.qianxin.com/saas/software/distribution/v1/client/software";
define('CACHE_TIME', 300);

// 输入参数
$softid = $_GET['softid'] ?? '';

// 参数验证
if (!is_numeric($softid) || strlen($softid) > 10) {
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

// 构建请求数据
$data = [
    'type' => 1,
   'soft_id' => $softid,
];

// 生成HuFuToken
function uuid() {
    $chars = md5(uniqid(mt_rand(), true));
    $uuid = substr($chars, 0, 8) . '-'
        . substr($chars, 8, 4) . '-'
        . substr($chars, 12, 4) . '-'
        . substr($chars, 16, 4) . '-'
        . substr($chars, 20, 12);
    return $uuid;
}
$HuFuToken = uuid();
header("ReqSendID: " . $HuFuToken);

// 发起请求
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $BASE_URL . '/download',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => array(
        'User-Agent: Windows apphost 1.0.0.155',
        'Platform-Type: Personal',
        'HuFuToken: ' . $HuFuToken,
        'Content-Type: application/json'
    ),
));
$response = curl_exec($ch);
curl_close($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error()!== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['url'];

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
