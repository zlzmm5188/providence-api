<?php
namespace think;

// ThinkPHP6 应用入口
define('APP_PATH', __DIR__ . '/../app/');

// 加载基础文件
require __DIR__ . '/../vendor/autoload.php';

// 统一 CORS 配置（只在这里设置一次）
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowedOrigins = ['https://admin.4kp3l0iq.top', 'https://4kp3l0iq.top', 'http://localhost:5173', 'http://localhost:8080', 'http://localhost:8083', 'http://127.0.0.1:8080', 'http://127.0.0.1:8083'];
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Token, token, Accept, Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// 创建应用实例
$app = new App();

// 在应用初始化后加载路由
$app->boot();

// 手动加载路由文件
require __DIR__ . '/../route/app.php';

// 执行HTTP应用并响应
$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
