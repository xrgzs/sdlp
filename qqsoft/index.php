<?php
// 输入参数
$cmdid = $_GET['cmdid'] ?? ''; // 获取传入的 cmdid 参数

// 检查参数
if (!is_numeric($cmdid)) {
    echo '输入参数不合法！';
    exit;
}

// 构建请求数据
$data = [
    'cmdid' => $cmdid,
    'jprxReq[req][soft_id_list][]' => 2,
];

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://luban.m.qq.com/api/public/software-manager/softwareProxy');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

// 发起 POST 请求
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
$downloadUrl = $jsonResponse['resp']['soft_list'][0]['download_url'];

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    echo '未找到下载链接。';
}
exit;
?>