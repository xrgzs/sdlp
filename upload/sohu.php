<?php

// 检查是否有文件上传
if (isset($_FILES['file'])) {
    // 文件信息
    $file = $_FILES['file'];

    // 检查文件是否为空
    if ($file['size'] == 0) {
        $error = ['status' => 'error', 'code' => 400, 'message' => 'Uploaded file is empty.'];
        header('Content-Type: application/json');
        echo json_encode($error);
        exit(); // 终止脚本执行
    }

    // 检查文件类型，只允许图片
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        $error = ['status' => 'error', 'code' => 400, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
        header('Content-Type: application/json');
        echo json_encode($error);
        exit(); // 终止脚本执行
    }
    
    // 生成一个随机文件名
    $random_string = bin2hex(random_bytes(8)); // 使用8个字节生成一个随机十六进制字符串
    $random_filename = pathinfo($file['name'], PATHINFO_EXTENSION) ? $random_string . '.' . pathinfo($file['name'], PATHINFO_EXTENSION) : $random_string;

    // 构建POST请求数据
    $postData = array(
        'file' => new CURLFile($file['tmp_name'], $file['type'], $random_filename)
    );

    // 目标URL
    $url = 'https://changyan.sohu.com/api/2/comment/attachment';

    // 初始化cURL会话
    $curl = curl_init();

    // 设置cURL选项
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // 执行cURL请求
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    // 反转义字符串
    $response = trim($response, '"');
    $response = stripslashes($response);

    // 处理响应
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (!empty($result['url'])) {
            $successResponse = [
                'status' => 'success', 
                'code' => $httpCode, 
                'data' => [
                    'url' => $result['url'],
                    'name' => $file['name'],
                    'os' => 'sohu'
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($successResponse);
        } else {
            $error = ['status' => 'error', 'code' => 400, 'message' => $result];
            header('Content-Type: application/json');
            echo json_encode($error);
        }
    } else {
        $error = ['status' => 'error', 'code' => $httpCode, 'message' => '请求失败！'];
        header('Content-Type: application/json');
        echo json_encode($error);
    }

    // 关闭cURL会话
    curl_close($curl);
} else {
    $error = ['status' => 'error', 'code' => 404, 'message' => 'No file uploaded.'];
    header('Content-Type: application/json');
    echo json_encode($error);
}
?>
