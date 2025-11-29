<?php

namespace app\providence\controller;

use think\facade\Db;
use think\facade\Request;

class Admin
{
    /**
     * 管理员登录
     */
    public function login()
    {
        $username = Request::post("username");
        $password = Request::post("password");

        if (empty($username) || empty($password)) {
            return json([
                "code" => 0,
                "message" => "用户名和密码不能为空",
                "result" => null
            ]);
        }

        // 查询管理员
        $admin = Db::name("admins")
            ->where("username", $username)
            ->where("status", 1)
            ->find();

        if (!$admin) {
            return json([
                "code" => 0,
                "message" => "管理员不存在",
                "result" => null
            ]);
        }

        // 验证密码
        if (!password_verify($password, $admin["password"])) {
            return json([
                "code" => 0,
                "message" => "密码错误",
                "result" => null
            ]);
        }

        // 生成token（使用JWT格式与前台一致）
        $secret = config('jwt.secret'); // 使用统一的JWT密钥
        $payload = [
            'iss' => 'providence_admin',
            'iat' => time(),
            'exp' => time() + 86400 * 7, // 延长到7天
            'id' => $admin['id'],           // 优先使用 id（与AuthMiddleware保持一致）
            'admin_id' => $admin['id'],     // 兼容 admin_id
            'user_id' => $admin['id']       // 兼容 user_id（用于访问用户接口）
        ];
        $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

        // 更新登录信息
        Db::name('admins')
            ->where('id', $admin['id'])
            ->update([
                'last_login_ip' => Request::ip(),
                'last_login_at' => date('Y-m-d H:i:s')
            ]);

        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "message" => "ok",
            "data" => [
                "token" => $token,  // 前端期望 data.token 而不是 data.accessToken
                "accessToken" => $token,  // 保留兼容性
                "refreshToken" => $token,
                "id" => $admin["id"],
                "username" => $admin["username"],
                "realName" => $admin["realname"] ?? "Administrator",
                "roles" => ["admin"],
                "admin" => [  // 前端可能期望 data.admin
                    "id" => $admin["id"],
                    "username" => $admin["username"],
                    "realname" => $admin["realname"] ?? "Administrator"
                ]
            ]
        ]);
    }

    /**
     * 获取管理员信息
     */
    public function getUserInfo()
    {
        // 从请求头获取token
        $token = Request::header('Authorization') ?? Request::header('Token') ?? '';
        $token = str_replace('Bearer ', '', $token);

        // 简单验证（生产环境应该验证JWT）
        if (empty($token)) {
            return json([
                "code" => 401,
                "message" => "未授权",
                "data" => null
            ]);
        }

        try {
            // 解码JWT获取管理员ID
            $secret = config('jwt.secret'); // 使用统一的JWT密钥
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
            $adminId = $decoded->admin_id ?? 1;

            // 查询管理员信息
            $admin = Db::name('admins')->where('id', $adminId)->find();

            if (!$admin) {
                return json([
                    "code" => 401,
                    "message" => "管理员不存在",
                    "data" => null
                ]);
            }

            return json([
                "code" => 1,  // 前端期望 code === 1 表示成功
                "message" => "ok",
                "data" => [
                    "id" => $admin['id'],
                    "username" => $admin['username'],
                    "realName" => $admin['realname'] ?? "Administrator",
                    "avatar" => "https://api.dicebear.com/7.x/miniavs/svg?seed=" . $admin['id'],
                    "roles" => [
                        [
                            "id" => "admin",
                            "code" => "admin",
                            "name" => "管理员",
                            "status" => 1
                        ]
                    ],
                    "permissions" => ["*:*:*"]
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                "code" => 401,
                "message" => "Token无效",
                "data" => null
            ]);
        }
    }

    /**
     * 获取权限码
     */
    public function getPermissions()
    {
        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "message" => "ok",
            "result" => ["*:*:*"]
        ]);
    }

    /**
     * 刷新token
     */
    public function refreshToken()
    {
        $oldToken = Request::header('Authorization') ?? Request::header('Token') ?? '';
        $oldToken = str_replace('Bearer ', '', $oldToken);

        try {
            // 解码旧token
            $secret = config('jwt.secret'); // 使用统一的JWT密钥
            $decoded = \Firebase\JWT\JWT::decode($oldToken, new \Firebase\JWT\Key($secret, 'HS256'));
            $adminId = $decoded->admin_id ?? 1;

            // 生成新token
            $payload = [
                'iss' => 'providence_admin',
                'iat' => time(),
                'exp' => time() + 7200,
                'admin_id' => $adminId,
                'user_id' => $adminId // 同时设置user_id，兼容AuthMiddleware
            ];
            $newToken = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

            return json([
                "code" => 1,  // 前端期望 code === 1 表示成功
                "message" => "ok",
                "result" => [
                    "accessToken" => $newToken,
                    "refreshToken" => $newToken
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                "code" => 401,
                "message" => "Token无效",
                "result" => null
            ]);
        }
    }

    /**
     * 获取权限代码（管理员拥有所有权限）
     */
    public function codes()
    {
        // 从请求头获取token
        $token = Request::header('Authorization') ?? Request::header('Token') ?? '';
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return json([
                "code" => 401,
                "message" => "未授权",
                "data" => []
            ]);
        }

        try {
            // 解码JWT验证管理员身份
            $secret = config('jwt.secret'); // 使用统一的JWT密钥
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));

            // 管理员拥有所有权限代码
            $codes = [
                'AC_100100', // 系统管理
                'AC_100110', // 用户管理
                'AC_100120', // 角色管理
                'AC_100130', // 菜单管理
                'AC_100140', // 权限管理
                'AC_200100', // 内容管理
                'AC_200110', // 文章管理
                'AC_200120', // 分类管理
                '*' // 所有权限
            ];

            return json([
                "code" => 1,  // 前端期望 code === 1 表示成功
                "message" => "ok",
                "data" => $codes
            ]);
        } catch (\Exception $e) {
            return json([
                "code" => 401,
                "message" => "Token无效",
                "data" => []
            ]);
        }
    }

    /**
     * 登出
     */
    public function logout()
    {
        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "message" => "ok",
            "result" => null
        ]);
    }
}
