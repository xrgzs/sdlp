<?php
$name = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);

// 检查参数
if (empty($name)) {
    http_response_code(400);
    die('未定义必需参数 name !');
}
if (strlen($name) > 20) {
    http_response_code(400);
    die('输入参数过长！');
}

// 发起 GET 请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.hotpe.top/API/HotPE/GetHPMList/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 遍历 data 数组，获取所有匹配名称的项
$downloadItems = [];
foreach ($jsonResponse['data'] as $sort) {
    foreach ($sort['list'] as $item) {
        if (str_starts_with(($item['name']), $name) !== false) {
            $downloadItems[] = $item;
        }
    }
}

// 自定义比较函数，用于比较两个日期字符串
function compareModified($a, $b) {
    $dateA = strtotime($a['modified']);
    $dateB = strtotime($b['modified']);
    return $dateA - $dateB;
}

// 使用usort和自定义比较函数对数组进行排序
usort($downloadItems, 'compareModified');
// 获取排序后数组的最后一项，即最新日期
$newestItem = end($downloadItems);

// 输出最新的modified的下载地址
if (!empty($newestItem['link'])) {
    $url = $newestItem['link'];
    header("Location: $url");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;