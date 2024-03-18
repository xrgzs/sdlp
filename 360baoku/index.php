<?php
// 输入参数
$appId = $_GET['appid']; // 获取传入的 appid 参数

// 目标网页 URL
$url = 'http://baoku.360.cn/soft/show/appid/' . $appId;

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 执行 cURL 请求
$html = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    echo 'cURL 请求出错：' . curl_error($ch);
    exit;
}

// 关闭 cURL
curl_close($ch);

// 使用 DOM 解析 HTML
$dom = new DOMDocument;
libxml_use_internal_errors(true); // 忽略 HTML 解析错误
$dom->loadHTML($html);
libxml_clear_errors();

// 查找按钮元素
$xpath = new DOMXPath($dom);
$button = $xpath->query("//a[contains(@class, 'normal-down')]")->item(0);

if ($button) {
    // 提取 href 属性
    $downloadLink = $button->getAttribute('href');
    
    // 直接跳转
    header("Location: $downloadLink");
    exit;
} else {
    echo '未找到下载链接按钮。';
}
?>