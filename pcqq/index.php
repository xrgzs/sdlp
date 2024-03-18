<?php

// 输入参数
$param = $_GET['param'] ?? 'downloadUrl'; // 获取传入的 cmdid 参数


// 目标网页 URL
$url = 'https://cdn-go.cn/qq-web/im.qq.com_new/latest/rainbow/windowsDownloadUrl.js';

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// 发起 POST 请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    echo 'cURL 请求出错：' . curl_error($ch);
    exit;
}

// 关闭 cURL
curl_close($ch);


// 根据描述，从第2行第26个字符开始截取至倒数第二个字符
$lines = explode("\n", $response);
$json_portion = substr($lines[1], 31); // PHP数组下标从0开始，因此第2行实际上是数组下标1
$json_portion = rtrim($json_portion, ';'); // 去掉末尾的分号

// 解析 JSON 响应
$params_array = json_decode($json_portion, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析失败: ' . json_last_error_msg());
}


// 验证参数是否存在于 JSON 数据中，避免非法访问
if (!isset($params_array[$param])) {
    die("无效的参数值");
}

// 获取下载地址
$downloadUrl = $params_array[$param];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo "未找到下载链接。";
}
exit;
?>