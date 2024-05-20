<?php

// 目标网页 URL 参数
$platform = isset($_GET['lite']) && $_GET['lite'] ? 5 : 0;

$url = "https://api.raylink.live/api/manage/client/version/queryClientVersion?platform={$platform}";

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 发起 GET 请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['data']['downloadLink'];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo '未找到下载链接。';
}
exit;