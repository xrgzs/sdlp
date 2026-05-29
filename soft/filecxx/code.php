<?php
// APCU 缓存配置
$cacheKey = 'filecxx_code'; // 唯一缓存键名
$cacheTTL = 3600; // 缓存有效期 1 小时（秒）

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $cachedResult = apcu_fetch($cacheKey);
    if ($cachedResult !== false) {
        echo $cachedResult;
        exit;
    }
}

// 执行cURL会话（带重试）
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://filecxx.com/zh_CN/activation_code.html");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$maxRetries = 2;
$retryDelay = 300;
$response = false;
for ($i = 0; $i <= $maxRetries; $i++) {
    $response = curl_exec($ch);
    if ($response !== false && curl_errno($ch) === 0) break;
    if ($i < $maxRetries) usleep($retryDelay * 1000);
}
// 检查是否有错误发生
if ($response === false) {
    http_response_code(500);
    die("cURL Error: " . curl_error($ch));
}
curl_close($ch);

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
    $result = $activationCodes[0] . PHP_EOL;
    // 仅在找到有效激活码时才缓存
    if (function_exists('apcu_store') && !empty($activationCodes)) {
        apcu_store($cacheKey, $result, $cacheTTL);
    }
} else {
    $result = "No valid activation code found." . PHP_EOL;
}

echo $result;

?>