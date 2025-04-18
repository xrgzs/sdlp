<?php
// APCU 缓存配置
$cacheKeyPrefix = 'scoop'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 输入参数
$name = isset($_GET['name']) ? $_GET['name'] : ''; // 获取传入的 name 参数
$bucket = isset($_GET['bucket']) ? $_GET['bucket'] : 'ScoopInstaller/Main'; // 获取传入的 bucket 参数
$branch = isset($_GET['branch']) ? $_GET['branch'] : 'master'; // 获取传入的 branch 参数
$arch = isset($_GET['arch']) ? $_GET['arch'] : '64bit'; // 获取传入的 arch 参数

// 检查参数
if (!is_string($name) || empty($name) || strlen($name) > 50) {
    http_response_code(400);
    die('输入 name 参数不合法！');
}
if (!is_string($bucket) || strlen($bucket) > 30) {
    http_response_code(400);
    die('输入 bucket 参数不合法！');
}
if (!is_string($branch) || strlen($branch) > 10) {
    http_response_code(400);
    die('输入 branch 参数不合法！');
}
if (!in_array($arch, ['32bit', '64bit', 'arm64'], true)) {
    http_response_code(400);
    die('输入 arch 参数不合法！');
}

// 进一步过滤和转义输入，防止注入
$name = htmlspecialchars($name);
$bucket = htmlspecialchars($bucket);
$branch = htmlspecialchars($branch);
// $arch = htmlspecialchars($arch);

// 生成缓存键
$cacheKey = $cacheKeyPrefix . $name . $bucket . $branch . $arch;

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl !== false) {
        // 命中缓存，直接重定向并设置响应头
        header("Location: $downloadUrl");
        exit;
    }
}

// 请求数据
$ghurl = "https://raw.githubusercontent.com/$bucket/refs/heads/$branch/bucket/$name.json";
// header("Scoop-Url: $ghurl");
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => 'https://gh.xrgzs.top/' . $ghurl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_MAXFILESIZE => 1 * 1024 * 1024, // 设置最大文件大小为1MB
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.6261.95 Safari/537.36'
    ),
));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}
curl_close($ch);

// 解析 JSON 响应
$jsonResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 获取下载地址
$downloadUrl = $jsonResponse['architecture'][$arch]['url'] ?? $jsonResponse['url'] ?? '';

// 从下载URL中移除"#/"之后的内容
$downloadUrl = explode('#/', $downloadUrl)[0];

// 检查是否以"https://github.com"开头，并添加前缀
if (strpos($downloadUrl, 'https://github.com/') === 0) {
    $downloadUrl = 'https://gh.xrgzs.top/' . $downloadUrl;
}

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && $downloadUrl !== false) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL); // 存储时自动覆盖旧缓存
}

// 跳转到下载地址
if (!empty($downloadUrl)) {
    header("Location: $downloadUrl");
} else {
    http_response_code(404);
    die('未找到下载链接。');
}
exit;
