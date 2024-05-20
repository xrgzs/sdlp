<?php

// 输入参数
$param = $_GET['param'] ?? 'x64'; // 获取传入的参数


// 目标网页 URL
$url = 'https://hudong.alicdn.com/api/data/v2/f11f572338d74092b8d87bf32791bbc0.js';

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// 发起 GET 请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

$json_portion = substr($response, 16);

// 解析 JSON 响应
$params_array = json_decode($json_portion, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 验证参数是否存在于 JSON 数据中，避免非法访问
if (!isset($params_array['WindowsVersion'][$param])) {
    die("无效的参数值");
}

// 获取下载地址
$downloadUrl = $params_array['WindowsVersion'][$param];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo "未找到下载链接。";
}
exit;