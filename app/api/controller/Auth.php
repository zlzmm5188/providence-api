<?php
namespace app\api\controller;

use app\common\model\User;
use app\common\model\UserRelation;
use app\common\model\SystemConfig;
use app\common\service\TelegramBotService;
use think\facade\Db;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    /**
     * 用户注册
     */
    public function register()
    {
        // 确保加载helper函数
        if (!function_exists('check_username')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        // 设置响应头，确保返回JSON
        header('Content-Type: application/json; charset=utf-8');

        try {
            $username = input('username');
            $password = input('password');
            $inviteCode = input('invite_code', '');

            // 1. 验证用户名
            if (!check_username($username)) {
                return error('用户名格式错误（4-20位字母数字下划线）');
            }

            // 2. 检查用户名是否存在
            if (User::where('username', $username)->count() > 0) {
                return error('用户名已存在');
            }

            // 3. 验证密码
            if (strlen($password) < 6 || strlen($password) > 20) {
                return error('密码长度必须为6-20位');
            }

            // 4. 验证邀请码并识别上级
            // 规则：邀请码 = 账号（username），统一转换为大写
            $parentId = null;
            try {
                $requireInviteCode = SystemConfig::where('key', 'require_invite_code')
                    ->value('value');
            } catch (\Exception $e) {
                // 如果系统配置表不存在或查询失败，默认不强制要求邀请码
                $requireInviteCode = 0;
            }

            if ($requireInviteCode == 1) {
                // 强制要求邀请码
                if (empty($inviteCode)) {
                    return error('请填写邀请码');
                }

                // 统一转换为大写（邀请码规则：等于账号，大写格式）
                $inviteCodeUpper = strtoupper(trim($inviteCode));
                $inviteCodeOriginal = trim($inviteCode);

                // 第一步：精确匹配（邀请码 = 账号）
                $parent = User::where('invite_code', $inviteCodeUpper)
                    ->whereOr('username', $inviteCodeUpper)
                    ->find();

                // 第二步：如果没找到，且输入的是纯数字，尝试匹配账号以该数字结尾的用户
                // 例如：输入138688，匹配G138688（账号以138688结尾）
                if (!$parent && ctype_digit($inviteCodeOriginal)) {
                    // 查找账号以该数字结尾的用户（最精确的匹配）
                    $parent = User::where('username', 'like', '%' . $inviteCodeOriginal)
                        ->whereOr('invite_code', 'like', '%' . $inviteCodeOriginal)
                        ->find();
                }

                // 第三步：如果还是没找到，尝试匹配账号包含该数字的用户（降级方案）
                if (!$parent && ctype_digit($inviteCodeOriginal)) {
                    $parent = User::where('username', 'like', '%' . $inviteCodeOriginal . '%')
                        ->whereOr('invite_code', 'like', '%' . $inviteCodeOriginal . '%')
                        ->find();
                }

                if (!$parent) {
                    return error('邀请码不存在，请检查邀请码是否正确');
                }
                // User模型主键是id，不是user_id
                $parentId = $parent->id;
            } else {
            // 邀请码可选
            if (!empty($inviteCode)) {
                // 统一转换为大写
                $inviteCodeUpper = strtoupper(trim($inviteCode));
                $inviteCodeOriginal = trim($inviteCode);

                // 第一步：精确匹配（邀请码 = 账号）
                $parent = User::where('invite_code', $inviteCodeUpper)
                    ->whereOr('username', $inviteCodeUpper)
                    ->find();

                // 第二步：如果没找到，且输入的是纯数字，尝试匹配账号以该数字结尾的用户
                if (!$parent && ctype_digit($inviteCodeOriginal)) {
                    $parent = User::where('username', 'like', '%' . $inviteCodeOriginal)
                        ->whereOr('invite_code', 'like', '%' . $inviteCodeOriginal)
                        ->find();
                }

                // 第三步：如果还是没找到，尝试匹配账号包含该数字的用户（降级方案）
                if (!$parent && ctype_digit($inviteCodeOriginal)) {
                    $parent = User::where('username', 'like', '%' . $inviteCodeOriginal . '%')
                        ->whereOr('invite_code', 'like', '%' . $inviteCodeOriginal . '%')
                        ->find();
                }

                if (!$parent) {
                    return error('邀请码不存在，请检查邀请码是否正确');
                }
                // User模型主键是id，不是user_id
                $parentId = $parent->id;
            }
        }

        Db::startTrans();

        try {
                // 5. 生成唯一uid
                $uid = generate_unique_uid();

                // 6. 邀请码规则：邀请码 = 个人账号（username）
                // 规则：code = 个人账号（系统规则）
                // 所有邀请码都是账号本身，不再使用随机生成的邀请码
                $inviteCodeNew = strtoupper($username); // 转换为大写，统一格式

                // 检查邀请码是否已存在（理论上不会发生，因为username唯一）
                $existingUser = User::where('invite_code', $inviteCodeNew)->find();
                if ($existingUser) {
                    Db::rollback();
                    return error('邀请码冲突，请使用其他账号名');
                }

                // 7. 创建用户
                // 先获取parent_uid（如果存在parent_id）
                $parentUid = null;
                if ($parentId) {
                    $parentUser = User::find($parentId);
                    $parentUid = $parentUser ? $parentUser->uid : null;
                }

                // 创建用户数据数组
                $userData = [
                    'uid' => $uid,
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'invite_code' => $inviteCodeNew,
                    'parent_id' => $parentId,
                    'parent_uid' => $parentUid,
                    'vip_level' => 0,
                    'status' => 1
                ];

                // 创建用户
                $user = User::create($userData);
                // 刷新模型以获取自增ID
                $user->refresh();

                // 8. 建立推荐关系
                if ($parentId) {
                    try {
                        // 一级关系
                        UserRelation::create([
                            'parent_id' => $parentId,
                            'user_id' => $user->id,  // 使用id作为主键
                            'level' => 1
                        ]);

                        // 二级关系
                        $parent = User::find($parentId);
                        if ($parent && $parent->parent_id) {
                            UserRelation::create([
                                'parent_id' => $parent->parent_id,
                                'user_id' => $user->id,  // 使用id作为主键
                                'level' => 2
                            ]);
                        }
                    } catch (\Exception $relationError) {
                        // 推荐关系创建失败不影响注册，只记录日志
                        \think\facade\Log::warning('创建推荐关系失败: ' . $relationError->getMessage());
                    }
                }

                Db::commit();

                // 9. 发送Telegram通知（可选，失败不影响注册）
                try {
                    TelegramBotService::notifyUserRegister([
                        'username' => $user->username,
                        'uid' => $user->uid,
                        'invite_code' => $user->invite_code,
                        'parent_id' => $parentId,
                        'ip' => request()->ip()
                    ]);
                } catch (\Exception $telegramError) {
                    // Telegram通知失败不影响注册流程，记录详细日志
                    \think\facade\Log::warning('Telegram通知失败', [
                        'error' => $telegramError->getMessage(),
                        'trace' => $telegramError->getTraceAsString(),
                        'username' => $user->username,
                        'uid' => $user->uid
                    ]);
                } catch (\Throwable $telegramError) {
                    // 捕获所有错误类型
                    \think\facade\Log::error('Telegram通知致命错误', [
                        'error' => $telegramError->getMessage(),
                        'trace' => $telegramError->getTraceAsString()
                    ]);
                }

                // 10. 生成Token（使用id字段）
                $userId = $user->id;  // 主键是id
                $token = $this->createToken($userId);

                // 返回格式符合前端要求
                return success('注册成功', [
                    'uid' => (int)$userId,  // 用户ID（主键id）
                    'token' => $token,      // JWT Token
                    'account' => $user->username,  // 账号
                    'avatar' => $user->avatar ?? "https://api.dicebear.com/7.x/miniavs/svg?seed={$user->username}",  // 头像URL
                    // 兼容字段
                    'user_id' => (int)$userId,
                    'id' => (int)$userId,
                    'username' => $user->username,
                    'invite_code' => $user->invite_code
                ]);

            } catch (\Exception $innerError) {
                Db::rollback();
                throw $innerError; // 重新抛出，让外层catch处理
            }

        } catch (\Exception $e) {
            // ThinkPHP 6 没有 getTransactionLevel 方法，直接尝试回滚
            try {
                Db::rollback();
            } catch (\Exception $rollbackError) {
                // 如果回滚失败（可能没有开启事务），忽略错误
            }
            // 记录详细错误日志
            \think\facade\Log::error('注册失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'username' => $username ?? '',
                'invite_code' => $inviteCode ?? ''
            ]);
            return error('注册失败：' . $e->getMessage());
        } catch (\Throwable $e) {
            // 捕获所有错误，包括致命错误
            try {
                Db::rollback();
            } catch (\Exception $rollbackError) {
                // 如果回滚失败（可能没有开启事务），忽略错误
            }
            \think\facade\Log::error('注册致命错误: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return error('注册失败：系统错误，请稍后重试');
        }
    }

    /**
     * 通用登录接口（支持用户和管理员）
     * 返回格式：{code:1, data:{accessToken, refreshToken, id, username, roles}}
     * 优先检查管理员，然后检查用户
     */
    public function login()
    {
        // 调试日志已移除 - 使用应用日志替代
        // file_put_contents('/tmp/login_debug.log', ...);

        $username = input('username');
        $password = input('password');

        if (!$username || !$password) {
            return json([
                "code" => -1,
                "msg" => "用户名和密码不能为空",
                "data" => null
            ]);
        }

        // 先检查管理员账号
        $admin = Db::name('admins')
            ->where('username', $username)
            ->where('status', 1)
            ->find();

        if ($admin && password_verify($password, $admin['password'])) {
            // 管理员登录成功
            // 更新登录信息
            Db::name('admins')->where('id', $admin['id'])->update([
                'last_login_time' => date('Y-m-d H:i:s'),
                'last_login_ip' => request()->ip()
            ]);

            // 生成管理员JWT token
            try {
                $secret = config('jwt.secret');
                $payload = [
                    'iss' => 'providence_admin',
                    'iat' => time(),
                    'exp' => time() + 86400 * 7,
                    'id' => $admin['id'],
                    'admin_id' => $admin['id'],
                    'username' => $admin['username'],
                    'is_admin' => true
                ];

                $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

                return json([
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
                return json([
                    'code' => -1,
                    'msg' => '登录失败：' . $e->getMessage(),
                    'data' => null
                ]);
            }
        }

        // 如果不是管理员，检查用户表
        $user = User::where('username', $username)->find();

        if (!$user) {
            return json([
                "code" => -1,
                "msg" => "用户名或密码错误",
                "data" => null
            ]);
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            return json([
                "code" => -1,
                "msg" => "用户名或密码错误",
                "data" => null
            ]);
        }

        // 检查账户状态
        if ($user->status != 1) {
            return json([
                'code' => -1,
                'msg' => '账户已被禁用',
                'data' => null
            ]);
        }

        // 更新登录信息
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->last_login_ip = request()->ip();
        $user->save();

        // 生成用户JWT token
        try {
            $secret = config('jwt.secret');
            $payload = [
                'iss' => 'providence_user',
                'iat' => time(),
                'exp' => time() + 86400 * 7,
                'id' => $user->id,
                'user_id' => $user->id,
                'username' => $user->username,
                'is_admin' => false
            ];

            $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

            // 返回后台期望的格式（兼容MineAdmin）
            return json([
                'code' => 1,
                'msg' => '登录成功',
                'data' => [
                    'accessToken' => $token,
                    'refreshToken' => $token,
                    'id' => $user->id,
                    'username' => $user->username,
                    'realName' => $user->username,
                    'roles' => ['user'],
                    'permissions' => ['user:*:*']
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => -1,
                'msg' => '登录失败：' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 登出
     */
    public function logout()
    {
        // Token在前端删除即可
        return success('登出成功');
    }

    /**
     * 生成JWT Token
     */
    private function createToken($userId)
    {
        $payload = [
            'iss' => 'providence',
            'iat' => time(),
            'exp' => time() + 7200, // 2小时
            'user_id' => $userId
        ];

        // 使用config()函数获取JWT密钥
        $secret = config('jwt.secret');
        if (empty($secret)) {
            throw new \Exception('JWT密钥未配置，请在config/jwt.php中设置secret');
        }
        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * 获取权限代码（代理到Admin控制器）
     */
    public function codes()
    {
        $admin = new \app\api\controller\Admin();
        return $admin->codes();
    }
}
