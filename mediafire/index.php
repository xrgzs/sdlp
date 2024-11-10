<?php

if (isset($_GET['url'])) {
    $inputurl = filter_var($_GET['url'], FILTER_VALIDATE_URL); // 验证URL有效性

    if ($inputurl === false) {
        http_response_code(500);
        echo "Error: 无效的URL";
        exit;
    }

  $pattern = '/^https?:\/\/www\.mediafire\.com\/(file|view|download)\/(\w+)\/(.*)/i';
    if (!preg_match($pattern, $inputurl, $matches)) {
        http_response_code(500);
        echo "Error: 无效的MediaFire链接";
        exit;
    }
   // 提取文件ID和文件名
    $filemode = $matches[1];
    $fileId = $matches[2];

    // 构建链接
    $url = "https://www.mediafire.com/{$filemode}/{$fileId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    if ($html === false) {
        http_response_code(500);
        echo "Error: 无法获取页面内容";
        exit;
    }

    // 正则表达式匹配下载链接
    $pattern = '/https?:\/\/download[0-9]+\.mediafire\.com\/[^\'"]+/';
    preg_match($pattern, $html, $matches);

    if (isset($matches[0])) {
        http_response_code(302);
        header("Location: " . $matches[0]);
        exit;
    } else {
		http_response_code(404);
        echo "Error: 无法找到有效的下载链接";
        exit;
    }
} else {
    http_response_code(500);
    echo "Error: 未提供URL";
    exit;
}
?>