<?php
// 获取 cmdid 参数
$cmdid = $_GET['cmdid'];

// 构建请求数据
$data = [
    'cmdid' => $cmdid,
    'jprxReq[req][soft_id_list][]' => 2,
];

// 发起 POST 请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://luban.m.qq.com/api/public/software-manager/softwareProxy');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
if(curl_errno($ch)) {
    die('cURL error: ' . curl_error($ch));
}
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON decoding error: ' . json_last_error_msg());
}
// 获取下载地址
$downloadUrl = $jsonResponse['resp']['soft_list'][0]['download_url'];

// 跳转到下载地址
header("Location: $downloadUrl");
exit;
?>