<?php

// 目标网页 URL
$url = "https://hao.360.com/2022.html?src=x";

// 初始化 cURL
$headers = array(
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'Accept-Encoding: gzip, deflate, br',
    'Accept-Language: zh-CN,zh;q=0.9',
    'Cache-Control: max-age=0',
    'Upgrade-Insecure-Requests: 1',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 发起请求
$html = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 创建DOMXPath对象
$xpath = new DOMXPath($dom);

// 执行XPath查询
$nodeList = $xpath->query('//style[starts-with(text(), "#tool-info .praise .icon")]');

// 处理查询结果
if ($nodeList->length > 0) {
    $styleNode = $nodeList->item(0);
    $cssRules = $styleNode->nodeValue;
    preg_match('/url\((.*?)\)/', $cssRules, $matches);
    if (isset($matches[1])) {
        $imageUrl = trim($matches[1], '"\'');
    }
}
if ($imageUrl) {
    // 直接跳转
    header("Location: " . $downloadLink);
} else {
    die('未找到下载链接按钮。');
}
exit;