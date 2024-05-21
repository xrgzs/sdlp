<?php

// 初始化 cURL
$curl = curl_init();

curl_setopt_array($curl, array(
   CURLOPT_URL => 'https://raw.onmicrosoft.cn/Bing-Wallpaper-Action/main/data/zh-CN_update.json',
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_HTTPHEADER => array(
      'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
   ),
));

// 发起请求
$response = curl_exec($curl);

// 检查是否有错误
if (curl_errno($ch)) {
    echo 'cURL 请求出错：' . curl_error($ch);
    exit;
}

// 关闭 cURL
curl_close($curl);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = 'https://s.cn.bing.net/' . $jsonResponse['images'][0]['url'];
if (!empty($downloadUrl)) {
    // 跳转到下载地址
    header("Location: $downloadUrl");
} else {
    echo '未找到下载链接。';
}
exit;