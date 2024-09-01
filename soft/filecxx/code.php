<?php
// 执行cURL会话
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://filecxx.com/zh_CN/activation_code.html"); // 请求的URL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 将结果作为字符串返回，而不是直接输出
curl_setopt($ch, CURLOPT_HEADER, false); // 不返回响应头
$response = curl_exec($ch);
curl_close($ch)
;
// 检查是否有错误发生
if ($response === false) {
    http_response_code(500);
    die("cURL Error: " . curl_error($ch));
}

// 使用DOMDocument解析HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true); // 忽略HTML中的错误
$dom->loadHTML($response);
libxml_clear_errors();

// 选择XPath处理器
$xpath = new DOMXPath($dom);
$nodes = $xpath->query('//*[@id="codes"]');

// 获取当前时间
$currentTime = new DateTime();

// 解析每个节点的内容
$activationCodes = []; // 用于存储所有符合条件的激活码

foreach ($nodes as $node) {
    $content = trim($node->nodeValue);
    if (preg_match_all('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\n(.+)/', $content, $matches)) {
        for ($i = 1; $i < count($matches[1]); $i++) {
            $startTime = new DateTime($matches[1][$i]);
            $endTime = new DateTime($matches[2][$i]);

            // 检查当前时间是否在时间段内
            if ($startTime <= $currentTime && $currentTime <= $endTime) {
                $activationCodes[] = $matches[3][$i];
            }
        }
    }
}

// 输出第一个符合条件的激活码
if (!empty($activationCodes)) {
    echo $activationCodes[0] . PHP_EOL;
} else {
    echo "No valid activation code found." . PHP_EOL;
}

?>