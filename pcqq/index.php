<?php
// 使用cURL获取内容
$url = 'https://cdn-go.cn/qq-web/im.qq.com_new/latest/rainbow/windowsDownloadUrl.js';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    die('cURL 请求失败: ' . curl_error($ch));
}
curl_close($ch);

// 根据描述，从第2行第26个字符开始截取至倒数第二个字符
$lines = explode("\n", $response);
$json_portion = substr($lines[1], 31); // PHP数组下标从0开始，因此第2行实际上是数组下标1
$json_portion = rtrim($json_portion, ';'); // 去掉末尾的分号

// 解析JSON并检查解析结果
$params_array = json_decode($json_portion, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $error_message = json_last_error_msg();
    die("无法获取或解析下载地址，JSON解析错误信息：{$error_message}");
}

// 获取并验证传入链接参数
$param = isset($_GET['param']) ? trim($_GET['param']) : ""; // 使用 trim 函数清理空格
if (empty($param)) {
    $param = "downloadUrl";
}

// 验证参数是否存在于 JSON 数据中，避免非法访问
if (!isset($params_array[$param])) {
    die("无效的参数值");
}

$latest_download_url = $params_array[$param];

// 使用 HTTP 302 重定向
header("Location: " . $latest_download_url, true, 302);
exit; // 添加 exit 函数确保程序在重定向后停止执行