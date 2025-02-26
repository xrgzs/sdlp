<?php
// 获取参数 name
$name = $_GET['name'];
if (empty($name)) {
    http_response_code(400);
    die('参数 name 不能为空。');
}
// 获取参数 x64
if (isset($_GET['x64'])) {
    $args = 'x64=1';
}

// 目标网页 URL 参数
$url = "https://client-api.oray.com/softwares/${name}?${args}";

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_REFERER, "https://sunlogin.oray.com/");

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
$downloadUrl = str_replace('dw.oray.com', 'down.oray.com', $jsonResponse['downloadurl']);
// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;