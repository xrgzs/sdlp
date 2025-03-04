<?php
// 定义服务器列表
$servers = array(
    'wetab.php',
    'itab.php',
    'bingrand.php'
);

// 随机选择一个服务器
$random_index = array_rand($servers);
$selected_server = $servers[$random_index];

// 重定向到随机选择的服务器
header("Location: " . $selected_server);
exit;
?>