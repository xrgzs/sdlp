<?php

// 直接定义参数数组
$params = [
    'src' => 0,
    'id' => 10266,
    'last_tag_ids' => '67,1',
    'ticker' => 3,
    'type' => 2,
    'aid' => 2,
    'uid' => 'b734f9e5814400829bcbe01e2c5c0e7a',
    'm' => 'acb4f28295d8a0dccfe0498431896e27',
    'm2' => 'eeeeeeee33a3a6d2a2af8145e03743bda65875632bbf',
    'v' => '22.3.3015.64'
];


// 基础URL
$baseUrl = "https://mini.browser.360.cn/newtab/ntget2";

// 将参数数组转换为URL查询字符串
$queryString = http_build_query($params);

// 合成完整的URL
$url = $baseUrl . '?' . $queryString;

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
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
$downloadUrl = $jsonResponse['data']['info']['img'];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo '未找到下载链接。';
}
exit;