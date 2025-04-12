<?php
// APCU 缓存配置
$cacheKeyPrefix = 'ghrelease'; // 唯一缓存键名
$cacheTTL = 600; // 缓存有效期 10 分钟（秒）

// 处理重定向函数
function handleLocation($url)
{
    if (!empty($mirror_name)) {
        $url = 'https://gh.xrgzs.top/' . $url;
    } else {
        $url = $url;
    }
    header("Location: $url");
}

// 获取传入的参数
$repo = $_GET['repo'];
$tag = $_GET['tag'] ?? ''; // 如果未指定 tag，则默认为空
$search = $_GET['search'] ?? ''; // 如果未指定 search，则默认为空
$filter = $_GET['filter'] ?? ''; // 如果未指定 filter，则默认为空
$mirror_name = $_GET['mirror'] ?? ''; // 如果未指定 mirror，则默认为空

// 检查参数
if (!is_string($repo) || empty($repo) || strlen($repo) > 50) {
    http_response_code(400);
    die('输入 repo 参数不合法！');
}
if (!is_string($tag) || strlen($tag) > 20) {
    http_response_code(400);
    die('输入 tag 参数不合法！');
}
if (!is_string($search) || strlen($search) > 50) {
    http_response_code(400);
    die('输入 search 参数不合法！');
}
if (!is_string($filter) || strlen($filter) > 50) {
    http_response_code(400);
    die('输入 filter 参数不合法！');
}
// if (!is_string($mirror_name) || strlen($mirror_name) > 10) {
//     http_response_code(400);
//     die('输入 mirror_name 参数不合法！');
// }

// 进一步过滤和转义输入，防止注入
$repo = htmlspecialchars($repo);
$tag = htmlspecialchars($tag);
$search = htmlspecialchars($search);
$filter = htmlspecialchars($filter);
// $mirror_name = htmlspecialchars($mirror_name);

// 确定 tags 的值
if (!empty($tag) && $tag != 'latest') {
    // 参考：https://docs.github.com/zh/rest/releases/releases#get-a-release-by-tag-name
    $tags = 'tags/' . $tag;
} else {
    $tags = 'latest';
}

// 生成缓存键
$cacheKey = $cacheKeyPrefix . $repo . $tag . $search . $filter;

// 尝试从 APCu 读取缓存
if (function_exists('apcu_enabled') && apcu_enabled()) {
    header("X-App-Cache: " . (apcu_exists($cacheKey) ? 'HIT' : 'MISS'));
    $downloadUrl = apcu_fetch($cacheKey);
    if ($downloadUrl !== false) {
        // 命中缓存，直接重定向并设置响应头
        handleLocation($downloadUrl);
        exit;
    }
}

// 构建 GitHub API URL
// 注意这里有请求限制，如需正式大量使用请使用缓存或本地代理
$api_url = "https://api.github.com/repos/{$repo}/releases/{$tags}";

// 初始化 cURL
$ch = curl_init($api_url);

// 设置 cURL 选项
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: MyGitHubAPI', // 设置 User-Agent
    'Accept: application/vnd.github.v3+json',
]);

// 发起请求
$response = curl_exec($ch);

// 检查是否有错误
if (curl_errno($ch)) {
    http_response_code(500);
    die('cURL 请求出错：' . curl_error($ch));
}

// 关闭 cURL
curl_close($ch);

// 解析 JSON 响应
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    die('JSON 解析失败: ' . json_last_error_msg());
}

// 查找匹配的 release 文件
foreach ($data['assets'] as $asset) {
    if (empty($search) || strpos($asset['name'], $search) !== false) {
        if (empty($filter) || str_ends_with($asset['name'], $filter) !== false) {
            $matching_assets = $asset['browser_download_url'];
            break;
        } else {
            $matching_assets = $asset['browser_download_url'];
        }
    }
}

// 输出匹配的文件链接
if (!empty($matching_assets)) {
    $downloadUrl = $matching_assets;
} else {
    http_response_code(404);
    die('未找到匹配的 release 文件。');
}

// 将新数据存入 APCu 缓存
if (function_exists('apcu_store') && $downloadUrl !== false) {
    apcu_store($cacheKey, $downloadUrl, $cacheTTL); // 存储时自动覆盖旧缓存
}

handleLocation($downloadUrl);
exit;
