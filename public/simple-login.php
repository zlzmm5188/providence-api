<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// 简单验证
if ($username === 'admin' && $password === 'admin123') {
    // 生成简单的token
    $token = base64_encode(json_encode([
        'id' => 1,
        'username' => 'admin',
        'time' => time()
    ]));

    echo json_encode([
        'code' => 1,
        'msg' => '登录成功',
        'data' => [
            'accessToken' => $token,
            'refreshToken' => $token,
            'id' => 1,
            'username' => 'admin',
            'roles' => ['admin']
        ]
    ]);
} else {
    echo json_encode([
        'code' => -1,
        'msg' => '用户名或密码错误',
        'data' => null
    ]);
}
