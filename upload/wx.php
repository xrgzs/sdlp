<?php

// 检查是否有文件上传
if (isset($_FILES['file'])) {
    // 文件信息
    $file = $_FILES['file'];

    // 构建POST请求数据
    $postData = array(
        'media' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
    );

    // 目标URL
    $url = 'https://openai.weixin.qq.com/weixinh5/webapp/h774yvzC2xlB4bIgGfX2stc4kvC85J/cos/upload';

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
                    'os' => 'zixi'
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($successResponse);
        } else {
            $error = ['status' => 'error', 'code' => $result['code'], 'message' => $result['error']];
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
