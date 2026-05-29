<?php
$type = trim(strip_tags(filter_input(INPUT_GET, 'type'))) ?? 'down';

if (!in_array($type, ['down', 'json'])) {
    http_response_code(400);
    die('TYPE不合法');
}

if (isset($_GET['url'])) {
    $inputurl = filter_var($_GET['url'], FILTER_VALIDATE_URL);

    if ($inputurl === false) {
        http_response_code(400);
        echo "Error: 无效的URL";
        exit;
    }

    // APCU 缓存配置
    $cacheKeyPrefix = 'mediafire_';
    $cacheTTL = 600;

    // 生成缓存键
    $cacheKey = $cacheKeyPrefix . md5($inputurl);

    // 尝试从 APCu 读取缓存
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
        $downloadUrl = apcu_fetch($cacheKey);
        if ($downloadUrl !== false) {
            if ($type === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['code' => 200, 'msg' => '解析成功', 'downUrl' => $downloadUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            header('Location: ' . $downloadUrl, true, 302);
            exit;
        }
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

    // 发起请求（带重试）
    $maxRetries = 2;
    $retryDelay = 300;
    $html = false;
    for ($i = 0; $i <= $maxRetries; $i++) {
        $html = curl_exec($ch);
        if ($html !== false && curl_errno($ch) === 0) break;
        if ($i < $maxRetries) usleep($retryDelay * 1000);
    }
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
        $downloadUrl = $matches[0];
        // 将新数据存入 APCu 缓存
        if (function_exists('apcu_store') && !empty($downloadUrl)) {
            apcu_store($cacheKey, $downloadUrl, $cacheTTL);
        }
        if ($type === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 200, 'msg' => '解析成功', 'downUrl' => $downloadUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        header('Location: ' . $downloadUrl, true, 302);
        exit;
    } else {
        http_response_code(404);
        echo "Error: 无法找到有效的下载链接";
        exit;
    }
} else {
    http_response_code(400);
    echo "Error: 未提供URL";
    exit;
}
?>