<?php
// 检查请求方法和Content-Type是否正确
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === false) {
    http_response_code(400);
    echo 'Invalid request';
    exit();
}

// 如果接收到文件
if(isset($_FILES['file'])) {
    // 获取文件信息
    $file = $_FILES['file'];

    // 读取文件内容并转换为Base64编码
    $imageData = file_get_contents($file['tmp_name']);
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

    // 执行请求并获取响应
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // 处理响应
    if ($httpCode === 200) {
        $result = $response;
        $random_number = rand(1, 8);
        $finalUrl = "https://pic{$random_number}.58cdn.com.cn/nowater/webim/big/{$result}";
        $successResponse = [
            'status' => 'success', 
            'data' => [
                'url' => $finalUrl,
                'name' => $_FILES['file']['name'],
                'os' => '58cdn'
            ]
        ];
        header('Content-Type: application/json');
        echo json_encode($successResponse);
    } else {
        http_response_code($httpCode);
        $error = ['status' => 'error', 'message' => $response];
        header('Content-Type: application/json');
        echo json_encode($error);
    }
    
    // 关闭 cURL 资源
    curl_close($ch);
} else {
    $error = ['status' => 'error', 'code' => 404, 'message' => '未收到文件！'];
    header('Content-Type: application/json');
    echo json_encode($error);
}
exit;