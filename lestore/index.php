<?php
// 定义常量
define('BASE_URL', "https://pc-store.lenovomm.cn/dlservice/getPcSoftDownloadUrlList?");

// 输入参数
$softid = $_GET['softid'] ?? '';

// 参数验证
if (!preg_match('/^[A-Za-z0-9]+$/', $softid)) { // 允许字母和数字组合
    http_response_code(400);
    die('输入参数不合法！');
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
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['downloadUrls'][0]['downLoadUrl'];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;