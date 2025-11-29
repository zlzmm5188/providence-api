<?php
// 快速测试脚本 - 不依赖ThinkPHP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 简单路由
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 登录接口
if ($uri === '/api/auth/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    // 简单验证（仅用于测试）
    if ($username && $password) {
        echo json_encode([
            'code' => 1,
            'msg' => '登录成功',
            'data' => [
                'token' => 'test_token_' . time(),
                'user_info' => [
                    'user_id' => 1,
                    'id' => 1,
                    'uid' => 1,
                    'username' => $username,
                    'vip_level' => 0,
                    'balance_cny' => '10000.00000000',
                    'balance_usdt' => '5000.00000000',
                    'invite_code' => '12345678'
                ]
            ]
        ]);
    } else {
        echo json_encode(['code' => -1, 'msg' => '用户名或密码不能为空', 'data' => []]);
    }
    exit;
}

// 获取用户信息 - profile.js会调用这个
if (preg_match('#/api/user#', $uri) || preg_match('#/user/user/index#', $uri)) {
    echo json_encode([
        'code' => 1,
        'msg' => '获取成功',
        'data' => [
            'user_id' => 1,
            'id' => 1,
            'uid' => 1,
            'username' => 'test',
            'real_name' => '测试用户',
            'vip_level' => 0,
            'total_invest' => '0.00000000',
            'balance_cny' => '10000.00000000',
            'balance_usdt' => '5000.00000000',
            'frozen_cny' => '0.00000000',
            'frozen_usdt' => '0.00000000',
            'ribao_cny' => '0.00000000',
            'ribao_usdt' => '0.00000000',
            'points' => 1000,
            'is_kyc' => 0,
            'invite_code' => '12345678',
            'team_count' => 0,
            'team_count_1' => 0,
            'team_count_2' => 0
        ]
    ]);
    exit;
}

// 项目列表
if ($uri === '/api/project/index') {
    echo json_encode([
        'code' => 1,
        'msg' => '获取成功',
        'data' => [
            'total' => 2,
            'list' => [
                [
                    'project_id' => 1,
                    'name' => '稳健理财30天',
                    'rate' => '0.015000',
                    'cycle' => 30,
                    'min_amount' => '1000.00000000',
                    'max_amount' => '50000.00000000',
                    'total_amount' => '1000000.00000000',
                    'sold_amount' => '100000.00000000',
                    'currency' => 'CNY',
                    'status' => 1
                ],
                [
                    'project_id' => 2,
                    'name' => 'USDT理财60天',
                    'rate' => '0.025000',
                    'cycle' => 60,
                    'min_amount' => '500.00000000',
                    'max_amount' => '100000.00000000',
                    'total_amount' => '500000.00000000',
                    'sold_amount' => '50000.00000000',
                    'currency' => 'USDT',
                    'status' => 1
                ]
            ]
        ]
    ]);
    exit;
}

// 默认响应
echo json_encode([
    'code' => 1,
    'msg' => 'Providence API 正在运行',
    'data' => [
        'version' => '1.0.0',
        'time' => date('Y-m-d H:i:s')
    ]
]);
