<?php
// 测试登录脚本
require __DIR__ . '/../vendor/autoload.php';

use think\facade\Db;
use Firebase\JWT\JWT;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Token');

try {
    // 初始化ThinkPHP
    $app = new think\App();
    $app->initialize();

    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode([
            'code' => -1,
            'msg' => '用户名和密码不能为空',
            'data' => null
        ]);
        exit;
    }

    // 查询管理员
    $admin = Db::name('admins')
        ->where('username', $username)
        ->where('status', 1)
        ->find();

    if (!$admin) {
        echo json_encode([
            'code' => -1,
            'msg' => '用户名或密码错误',
            'data' => null
        ]);
        exit;
    }

    // 验证密码
    if (!password_verify($password, $admin['password'])) {
        echo json_encode([
            'code' => -1,
            'msg' => '用户名或密码错误',
            'data' => null
        ]);
        exit;
    }

    // 生成Token
    $secret = 'Providence2025SuperSecretKey';
    $payload = [
        'iss' => 'providence_admin',
        'iat' => time(),
        'exp' => time() + 86400 * 7,
        'id' => $admin['id'],
        'admin_id' => $admin['id'],
        'username' => $admin['username'],
        'is_admin' => true
    ];

    $token = JWT::encode($payload, $secret, 'HS256');

    echo json_encode([
        'code' => 1,
        'msg' => '登录成功',
        'data' => [
            'accessToken' => $token,
            'refreshToken' => $token,
            'id' => $admin['id'],
            'username' => $admin['username'],
            'roles' => ['admin'],
            'permissions' => ['*:*:*']
        ]
    ]);

} catch (\Exception $e) {
    echo json_encode([
        'code' => -1,
        'msg' => '登录失败：' . $e->getMessage(),
        'data' => null
    ]);
}
