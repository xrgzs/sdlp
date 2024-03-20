<?php
// 获取传入的参数
$product_id = $_GET['product_id'] ?? '2935';
$sku_id = $_GET['sku_id'] ?? '17436';
$arch = $_GET['arch'] ?? 'x64';

// 检查参数
if (!is_numeric($product_id) || !is_numeric($sku_id)) {
    echo '输入参数不合法！';
    exit;
}

// 目标网页 URL
$url = "https://massgrave.dev/api/msdl/proxy?product_id={$product_id}&sku_id={$sku_id}";

// 初始化 cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 设置代理（仅中国移动需要，电信、联通直连）
// curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
// curl_setopt($ch, CURLOPT_PROXY, '10.0.1.111:10809');

// 发起 POST 请求
$response = curl_exec($ch);

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
$dom->loadHTML($response);
libxml_clear_errors();

// 寻找所有的a标签
$anchors = $dom->getElementsByTagName('a');

// 遍历找到的a标签
foreach ($anchors as $anchor) {
    // 获取href属性值
    $href = $anchor->getAttribute('href');
    if ($anchor->nodeValue === "Iso{$arch} Download") {
        $downloadlink = $href;
        break; // 找到后就可以跳出循环
    }
}

// 跳转到下载地址
if (!empty($downloadlink)) {
    header("Location: $downloadlink");
} else {
    echo '未找到下载链接。';
}
exit;
?>