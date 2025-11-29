<?php
// PHP内置服务器路由文件

// 处理静态文件
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 如果是文件且存在，直接返回
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// 否则交给index.php处理
$_SERVER['SCRIPT_NAME'] = '/index.php';
require_once __DIR__ . '/index.php';
