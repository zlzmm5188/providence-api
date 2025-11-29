<?php
namespace app\api\controller;

use think\facade\Db;
use think\facade\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Admin
{
    /**
     * 获取管理员信息
     */
    public function info()
    {
        try {
            // 获取token
            $token = Request::header('Authorization');
            if (!$token) {
                return json(['code' => -1, 'msg' => '未登录', 'data' => null]);
            }

            // 去掉Bearer前缀
            $token = str_replace('Bearer ', '', $token);

            // 解析token
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));

            // 检查是否是管理员token
            if (!isset($payload->is_admin) || !$payload->is_admin) {
                return json(['code' => -1, 'msg' => '非管理员账号', 'data' => null]);
            }

            // 获取管理员信息
            $admin = Db::name('pd_admins')
                ->where('id', $payload->admin_id)
                ->where('status', 1)
                ->find();

            if (!$admin) {
                return json(['code' => -1, 'msg' => '管理员不存在', 'data' => null]);
            }

            // 返回管理员信息
            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'nickname' => $admin['nickname'] ?? $admin['username'],
                    'avatar' => $admin['avatar'] ?? 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $admin['username'],
                    'email' => $admin['email'] ?? '',
                    'phone' => $admin['phone'] ?? '',
                    'roles' => ['admin'],
                    'permissions' => ['*:*:*'],
                    'status' => $admin['status'],
                    'created_at' => $admin['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => 'Token验证失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取管理员列表
     */
    public function list()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 10);

            $admins = Db::name('pd_admins')
                ->where('status', 1)
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_admins')
                ->where('status', 1)
                ->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $admins,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }
}
