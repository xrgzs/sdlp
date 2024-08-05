<?php

// 输入参数
$param = $_GET['param'] ?? 'downloadUrl'; // 获取传入的 cmdid 参数

// 获取下载地址
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://im.qq.com/pcqq/index.shtml');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $url = 'https://cdn-go.cn/qq-web/im.qq.com_new/latest/rainbow/windowsDownloadUrl.js';
} else {
    // 使用正则表达式提取数据
    preg_match('/var rainbowConfigUrl = "(https:\/\/qq-web\.cdn-go\.cn\/im\.qq\.com_new\/.*)";/', $response, $matches);
    if (!empty($matches[1])) {
        $url = $matches[1];
    } else {
        $url = 'https://cdn-go.cn/qq-web/im.qq.com_new/latest/rainbow/windowsDownloadUrl.js';
    }
}
curl_close($ch);

// 请求接口
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
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
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 验证参数是否存在于 JSON 数据中，避免非法访问
if (!isset($params_array[$param])) {
    http_response_code(400);
    die("无效的参数值");
}

// 获取下载地址
$downloadUrl = str_replace('dldir1.qq.com', 'dldir1v6.qq.com', $params_array[$param]);

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die("未找到下载链接。");
}
exit;