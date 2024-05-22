<?php
// 目标 URL
$target_url = 'https://kf.dianping.com/api/file/singleImage';

// 如果接收到文件
if(isset($_FILES['file'])) {
    // 获取文件信息
    $file = $_FILES['file'];
    $headers = array(
        'Referer: https://h5.dianping.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
    );
    // 生成一个随机文件名
    $random_string = bin2hex(random_bytes(8)); // 使用8个字节生成一个随机十六进制字符串
    $random_filename = pathinfo($file['name'], PATHINFO_EXTENSION) ? $random_string . '.' . pathinfo($file['name'], PATHINFO_EXTENSION) : $random_string;
    // 准备 POST 数据
    $post_data = array(
        'channel' => '4',
        'file' => new CURLFile($file['tmp_name'], $file['type'], $random_filename)
    );

    // 初始化 cURL
    $curl = curl_init();

    // 设置 cURL 选项
    curl_setopt($curl, CURLOPT_URL, $target_url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // 执行请求并获取响应
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    // 处理响应
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['success'] === true) {
            $finalUrl = $result['data']['uploadPath'];
            $successResponse = [
                'status' => 'success', 
                'code' => $result['code'], 
                'data' => [
                    'url' => $finalUrl,
                    'name' => $file['name'],
                    'os' => 'dianping'
                ]
            ];
            header('Content-Type: application/json');
            echo json_encode($successResponse);
        } else {
            $error = ['status' => 'error', 'code' => $result['code'], 'message' => $result['errMsg']];
            header('Content-Type: application/json');
            echo json_encode($error);
        }
    } else {
        $error = ['status' => 'error', 'code' => $httpCode, 'message' => '请求失败！'];
        header('Content-Type: application/json');
        echo json_encode($error);
    }
    
    // 关闭 cURL 资源
    curl_close($curl);

} else {
    $error = ['status' => 'error', 'code' => 404, 'message' => '未收到文件！'];
    header('Content-Type: application/json');
    echo json_encode($error);
}
?>
