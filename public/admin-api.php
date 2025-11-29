<?php
// 管理后台API接口
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Token, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 获取请求路径
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/admin-api.php', '', $path);

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);

// 简单的token验证
function checkAuth() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $headers['Token'] ?? '';
    $token = str_replace('Bearer ', '', $token);

    // 简单验证（实际应该验证JWT）
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['code' => 401, 'msg' => '未授权']);
        exit();
    }
    return true;
}

// 路由处理
switch ($path) {
    case '/login':
        // 登录接口
        if ($method === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';

            if ($username === 'admin' && $password === 'admin123') {
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
                        'realName' => 'Administrator',
                        'roles' => ['admin'],
                        'permissions' => ['*:*:*']
                    ]
                ]);
            } else {
                echo json_encode([
                    'code' => -1,
                    'msg' => '用户名或密码错误',
                    'data' => null
                ]);
            }
        }
        break;

    case '/user/info':
        // 获取用户信息
        checkAuth();
        echo json_encode([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'id' => 1,
                'username' => 'admin',
                'realName' => 'Administrator',
                'avatar' => 'https://api.dicebear.com/7.x/miniavs/svg?seed=admin',
                'roles' => ['admin'],
                'permissions' => ['*:*:*']
            ]
        ]);
        break;

    case '/dashboard/stats':
        // 仪表盘统计
        checkAuth();
        echo json_encode([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'totalUsers' => 10,
                'totalInvest' => 15000000,
                'pendingOrders' => 8,
                'todayIncome' => 58340,
                'activeUsers' => 5,
                'totalWithdraw' => 2000000
            ]
        ]);
        break;

    case '/users/list':
        // 用户列表
        checkAuth();
        echo json_encode([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'total' => 10,
                'list' => [
                    [
                        'id' => 1,
                        'username' => 'G138688',
                        'vip_level' => 8,
                        'balance_cny' => 1000000,
                        'balance_usdt' => 10000,
                        'status' => 'active',
                        'created_at' => '2025-11-15 10:00:00'
                    ],
                    [
                        'id' => 2,
                        'username' => 'user_002',
                        'vip_level' => 3,
                        'balance_cny' => 5000,
                        'balance_usdt' => 500,
                        'status' => 'active',
                        'created_at' => '2025-11-20 11:00:00'
                    ]
                ]
            ]
        ]);
        break;

    case '/projects/list':
        // 项目列表
        checkAuth();
        echo json_encode([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'total' => 3,
                'list' => [
                    [
                        'id' => 1,
                        'name' => '新手体验项目',
                        'rate' => 1.2,
                        'duration' => 7,
                        'total_amount' => 100000,
                        'invested_amount' => 80000,
                        'status' => 'active'
                    ],
                    [
                        'id' => 2,
                        'name' => '稳健增值计划',
                        'rate' => 0.8,
                        'duration' => 30,
                        'total_amount' => 500000,
                        'invested_amount' => 450000,
                        'status' => 'active'
                    ]
                ]
            ]
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'code' => 404,
            'msg' => '接口不存在',
            'data' => null
        ]);
        break;
}
?>
