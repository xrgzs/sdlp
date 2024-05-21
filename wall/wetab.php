<?php

// 定义标签数组
$tags = ['nature','architecture','travel'];

// 随机选择一个标签
$tag = $tags[array_rand($tags)];

// 加载标签到请求头
header('Sort: ' . $tag);

// 目标网页 URL
$url = "https://api.wetab.link/api/wallpaper/random?client=pc&pageSize=1&tag=$tag";

// 初始化 cURL
$headers = array(
    'Accept: */*',
    'Accept-Encoding: gzip, deflate, br',
    'Accept-Language: zh-CN,zh;q=0.9',
    'Connection: keep-alive',
    'Host: api.wetab.link',
    'i-app: hitab',
    'i-branch: zh',
    'i-lang: zh-CN',
    'i-platform: chrome',
    'i-version: 1.7.0',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 发起请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    echo 'cURL 请求出错：' . curl_error($ch);
    exit;
}

// 关闭 cURL
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data'][0]['rawSrc'];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo '未找到下载链接。';
}
exit;