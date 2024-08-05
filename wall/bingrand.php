<?php

// 初始化 cURL
$curl = curl_init();

curl_setopt_array($curl, array(
   CURLOPT_URL => 'https://raw.onmicrosoft.cn/Bing-Wallpaper-Action/main/data/zh-CN_all.json',
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_HTTPHEADER => array(
      'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
   ),
));

// 发起请求
$response = curl_exec($curl);

// 检查是否有错误
if (curl_errno($curl)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($curl));
}

// 关闭 cURL
curl_close($curl);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取数组长度
$length = count($jsonResponse['data']);

// 根据长度生成一个随机索引
$random_index = mt_rand(0, $length - 1);

// 获取随机下载地址
$downloadUrl = 'https://s.cn.bing.net/' . $jsonResponse['data'][$random_index]['urlbase'] . '_UHD.jpg';
if (!empty($downloadUrl)) {
    // 跳转到下载地址
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;