<?php
// 检查请求方法和Content-Type是否正确
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false) {
    http_response_code(400);
    echo 'Invalid request';
    exit();
}

// 获取上传的图片文件
$imageFile = $_FILES['image']['tmp_name'];
if (!$imageFile) {
    http_response_code(400);
    echo 'Image file not found';
    exit();
}

// 读取文件内容并转换为Base64编码
$imageData = file_get_contents($imageFile);
$base64EncodedData = base64_encode($imageData);

// 构建请求数据
$payload = [
    "Pic-Size" => "0*0",
    "Pic-Encoding" => "base64",
    "Pic-Path" => "/nowater/webim/big/",
    "Pic-Data" => $base64EncodedData
];

// 目标URL
$targetUrl = "https://upload.58cdn.com.cn/json/nowater/webim/big/";

// 使用cURL发送POST请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// 处理响应
if ($httpCode === 200) {
    $result = $response;
    $random_number = rand(1, 8);
    $finalUrl = "https://pic{$random_number}.58cdn.com.cn/nowater/webim/big/{$result}";
    $successResponse = ['status' => 'success', 'data' => ['url' => $finalUrl]];
    header('Content-Type: application/json');
    echo json_encode($successResponse);
} else {
    http_response_code($httpCode);
    $error = ['status' => 'error', 'message' => $response];
    echo json_encode($error);
}
exit;