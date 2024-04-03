<?php
// 定义常量或配置项
define('BASE_URL', 'http://baoku.360.cn/soft/show/appid/');

// 输入参数
$appId = isset($_GET['appid']) ? $_GET['appid'] : '';

// 参数校验
if (!is_numeric($appId) || strlen($appId) > 10) {
    die('输入参数不合法！');
}

// 进行更严格的过滤或转义，防止URL注入
$appId = filter_var($appId, FILTER_SANITIZE_NUMBER_INT);

// 目标网页 URL
$url = BASE_URL . $appId;

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 执行 cURL 请求
$html = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 使用 DOM 解析 HTML
$dom = new DOMDocument;
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

// 查找按钮元素
$xpath = new DOMXPath($dom);
$button = $xpath->query("//a[contains(@class, 'normal-down')]")->item(0);

if ($button) {
    // 提取 href 属性
    $downloadLink = $button->getAttribute('href');
    // 直接跳转
    header("Location: " . $downloadLink);
} else {
    die('未找到下载链接按钮。');
}
exit;