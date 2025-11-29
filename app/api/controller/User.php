<?php
namespace app\api\controller;

use app\common\model\User as UserModel;
use app\common\model\UserRelation;
use app\common\model\WalletLog;
use think\facade\Db;

// 确保加载辅助函数
require_once dirname(__DIR__, 2) . '/common/helpers.php';

class User
{
    /**
     * 获取用户信息
     */
    /**
     * 首页快速加载（轻量级） - 只返回首页显示需要的最小数据
     * 避免多余的DB查询，提高首页加载速度
     */
    public function index()
    {
        // 使用统一的get_user_id函数（与AuthMiddleware保持一致）
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        // 按前端期望格式返回（数字，不是字符串）
        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => [
                'uid' => (int)$user->id,
                'account' => $user->username,
                'avatar' => $user->avatar ?? "https://api.dicebear.com/7.x/miniavs/svg?seed={$user->username}",
                'usdt' => (float)($user->balance_usdt ?? 0),
                'cny' => (float)($user->balance_cny ?? 0),
                'profit' => (float)($user->total_invest ?? 0),
                'invite_code' => $user->invite_code ?? '',
                'vip_level' => (int)($user->vip_level ?? 0)
            ]
        ]);
    }

    /**
     * 首页快速加载（轻量级）- 只返回显示在首页需要的数据
     * 用于首页快速展示，避免过多DB查询
     */
    public function dashboard()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        // 只返回首页必需的最小数据集
        return success('获取成功', [
            'id' => $user->id,
            'username' => $user->username,
            'vip_level' => $user->vip_level ?? 0,
            'balance_cny' => floatval($user->balance_cny ?? 0),
            'balance_usdt' => floatval($user->balance_usdt ?? 0),
            'ribao_cny' => floatval($user->ribao_cny ?? 0),
            'ribao_usdt' => floatval($user->ribao_usdt ?? 0),
            'total_invest' => floatval($user->total_invest ?? 0),
        ]);
    }

    /**
     * 获取用户信息（前端首页显示格式）
     * 按照前端要求的格式返回
     */
    public function info()
    {
        // 从请求头获取token
        $token = request()->header('Authorization') ?? request()->header('Token') ?? '';
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return json([
                "code" => 401,
                "msg" => "未授权",
                "data" => null
            ]);
        }

        try {
            // 尝试解码token（支持用户token和管理员token）
            $secret = config('jwt.secret');
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
            $userId = $decoded->user_id ?? null;
            $adminId = $decoded->admin_id ?? $decoded->id ?? null;  // 兼容id字段

            // 如果是管理员token（有admin_id或iss为providence_admin），返回管理员信息
            if ($adminId && ($decoded->iss ?? '') === 'providence_admin') {
                $admin = \think\facade\Db::name('admins')->where('id', $adminId)->find();
                if (!$admin) {
                    return json([
                        "code" => 401,
                        "msg" => "管理员不存在",
                        "data" => null
                    ]);
                }

                // 返回管理员格式（兼容前端期望的格式）
                return json([
                    'code' => 1,
                    'msg'  => 'ok',
                    'data' => [
                        'id' => $admin['id'],
                        'username' => $admin['username'],
                        'realName' => $admin['realname'] ?? "Administrator",
                        'avatar' => "https://api.dicebear.com/7.x/miniavs/svg?seed=" . $admin['id'],
                        'roles' => [
                            [
                                'id' => 'admin',
                                'code' => 'admin',
                                'name' => '管理员',
                                'status' => 1
                            ]
                        ],
                        'permissions' => ['*:*:*'],
                        'homePath' => '/dashboard'
                    ]
                ]);
            }

            // 如果是用户token，返回用户信息
            if (!$userId) {
                return json([
                    "code" => 401,
                    "msg" => "Token无效",
                    "data" => null
                ]);
            }

            // 查询用户信息
            $user = UserModel::find($userId);
            if (!$user) {
                return json([
                    "code" => 401,
                    "msg" => "用户不存在",
                    "data" => null
                ]);
            }

            // 按前端要求的格式返回
            return json([
                'code' => 1,
                'msg'  => 'ok',
                'data' => [
                    'uid'     => (int)$user->id,
                    'account' => $user->username,
                    'avatar'  => $user->avatar ?? "https://api.dicebear.com/7.x/miniavs/svg?seed={$user->username}",

                    // 金额字段必须是数字（不是字符串）
                    'usdt'    => (float)($user->balance_usdt ?? 0),
                    'cny'     => (float)($user->balance_cny ?? 0),
                    'profit'  => (float)($user->total_invest ?? 0),
                    'invite_code' => $user->invite_code ?? '',
                    'vip_level' => (int)($user->vip_level ?? 0)
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                "code" => 401,
                "msg" => "Token无效: " . $e->getMessage(),
                "data" => null
            ]);
        }
    }

    /**
     * 获取VIP进度
     */
    public function vipProgress()
    {
        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = UserModel::find($userId);

        if (!$user) {
            return error('用户不存在');
        }

        // 获取当前VIP配置
        $currentVip = \app\common\model\VipLevel::where('level', $user->vip_level)->find();

        // 获取下一级VIP配置
        $nextVip = \app\common\model\VipLevel::where('level', $user->vip_level + 1)->find();

        $data = [
            'current_level' => $user->vip_level,
            'current_name' => $currentVip ? $currentVip->name : 'VIP0',
            'total_invest' => $user->total_invest,
            'current_required' => $currentVip ? $currentVip->required_invest : '0.00000000'
        ];

        if ($nextVip) {
            $needInvest = bcmath_sub($nextVip->required_invest, $user->total_invest);
            $progress = 0;

            if ($currentVip && bcmath_comp($user->total_invest, $currentVip->required_invest) >= 0) {
                $total = bcmath_sub($nextVip->required_invest, $currentVip->required_invest);
                $current = bcmath_sub($user->total_invest, $currentVip->required_invest);
                if (bcmath_comp($total, '0') > 0) {
                    $progress = bcmath_mul(bcmath_div($current, $total), '100');
                }
            }

            $data['next_level'] = $nextVip->level;
            $data['next_name'] = $nextVip->name;
            $data['next_required'] = $nextVip->required_invest;
            $data['need_invest'] = $needInvest;
            $data['progress'] = round((float)$progress, 2);
        } else {
            $data['next_level'] = null;
            $data['next_name'] = '已达最高等级';
            $data['next_required'] = null;
            $data['need_invest'] = '0.00000000';
            $data['progress'] = 100;
        }

        return success('获取成功', $data);
    }

    /**
     * 获取日利宝头部信息
     */
    public function ribaoHead()
    {
        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = UserModel::find($userId);

        if (!$user) {
            return error('用户不存在');
        }

        return success('获取成功', [
            'ribao_cny' => $user->ribao_cny ?? '0.00000000',
            'ribao_usdt' => $user->ribao_usdt ?? '0.00000000',
            'daily_rate_cny' => '0.0020',  // 0.2% 日利率
            'daily_rate_usdt' => '0.0020',
            'today_earnings_cny' => bcmath_mul($user->ribao_cny ?? '0', '0.0020', 8),
            'today_earnings_usdt' => bcmath_mul($user->ribao_usdt ?? '0', '0.0020', 8),
        ]);
    }

    /**
     * 获取日利宝详细信息
     */
    public function ribaoInfo()
    {
        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = UserModel::find($userId);

        if (!$user) {
            return error('用户不存在');
        }

        return success('获取成功', [
            'ribao_cny' => $user->ribao_cny ?? '0.00000000',
            'ribao_usdt' => $user->ribao_usdt ?? '0.00000000',
            'balance_cny' => $user->balance_cny,
            'balance_usdt' => $user->balance_usdt,
            'daily_rate' => '0.0020',
            'total_earnings_cny' => '0.00000000', // 可以从统计表获取
            'total_earnings_usdt' => '0.00000000',
        ]);
    }

    /**
     * 用户登录（前台专用 - 旧格式）
     * 返回格式：{code:1, data:{uid, account, usdt, cny, profit, token}}
     * 与Auth.login的区别：只返回普通用户Token，使用旧格式
     */
    public function login()
    {
        // 调试日志：记录登录参数
        file_put_contents('/tmp/login_debug.log', json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => input('username'),
            'password_length' => strlen(input('password')),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'endpoint' => '/api/user/login'
        ]) . "\n", FILE_APPEND);

        $username = input('username');
        $password = input('password');

        if (!$username || !$password) {
            return json([
                'code' => -1,
                'msg' => '用户名和密码不能为空',
                'data' => null
            ]);
        }

        // 查询用户（不查询管理员）
        $user = UserModel::where('username', $username)->find();

        if (!$user) {
            return json([
                'code' => -1,
                'msg' => '用户名或密码错误',
                'data' => null
            ]);
        }

        // 验证密码
        if (!password_verify($password, $user->password)) {
            return json([
                'code' => -1,
                'msg' => '用户名或密码错误',
                'data' => null
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

        // 生成用户JWT token（统一格式：同时设置id和user_id，兼容前后台）
        try {
            $secret = config('jwt.secret'); // 使用统一的config密钥
            $payload = [
                'iss' => 'providence_user',
                'iat' => time(),
                'exp' => time() + 86400 * 7, // 延长到7天
                'id' => $user->id,           // 统一使用id（与AuthMiddleware保持一致）
                'user_id' => $user->id       // 同时设置user_id（兼容旧代码）
            ];

            $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');

            // 返回旧格式（code=1，字段uid/account/usdt/cny/profit）
            return json([
                'code' => 1,
                'msg' => '登录成功',
                'data' => [
                    'uid' => (int)$user->id,
                    'account' => $user->username,
                    'avatar' => $user->avatar ?? "https://api.dicebear.com/7.x/miniavs/svg?seed={$user->username}",
                    'usdt' => (float)($user->balance_usdt ?? 0),
                    'cny' => (float)($user->balance_cny ?? 0),
                    'profit' => (float)($user->total_invest ?? 0),
                    'token' => $token,  // 旧格式中token在data里
                    'invite_code' => $user->invite_code ?? '',
                    'vip_level' => (int)($user->vip_level ?? 0)
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
     * 获取日利宝转入转出记录
     */
    public function ribaoRecords()
    {
        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 10);
        $type = input('type', ''); // in/out

        // 这里应该查询ribao_logs表,暂时返回空列表
        return success('获取成功', [
            'total' => 0,
            'list' => []
        ]);
    }

    /**
     * 获取交易记录
     * GET /api/user/transaction/records
     */
    public function transactionRecords()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1, 'intval');
        $pageSize = input('pageSize', 20, 'intval');

        // 查询钱包流水记录
        $list = WalletLog::where('user_id', $userId)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = WalletLog::where('user_id', $userId)->count();

        // 格式化数据，匹配前端需要的格式
        $records = [];
        foreach ($list as $log) {
            $amount = floatval($log->amount);
            $typeName = $this->getTypeName($log->type, $log->currency);

            $records[] = [
                'id' => $log->id,
                'type_name' => $typeName,
                'money' => $amount,
                'currency' => $log->currency,
                'remark' => $log->remark ?: $typeName,
                'desc' => $log->remark ?: '',
                'created_at' => $log->created_at,
                'time' => $log->created_at,
                'balance_before' => $log->balance_before ?? 0,
                'balance_after' => $log->balance_after ?? 0
            ];
        }

        return success('获取成功', $records);
    }

    /**
     * 获取类型名称
     */
    private function getTypeName($type, $currency)
    {
        $typeMap = [
            'recharge' => '充值',
            'withdraw' => '提现',
            'invest' => '申购',
            'invest_profit' => '理财收益',
            'sign_reward' => '签到奖励',
            'team_reward' => '团队奖励',
            'ribao_profit' => '日利宝收益',
            'ribao_transfer_in' => '日利宝转入',
            'ribao_transfer_out' => '日利宝转出',
            'exchange_cny_to_usdt' => 'CNY兑换USDT',
            'exchange_usdt_to_cny' => 'USDT兑换CNY',
            'admin_balance_adjust' => '余额调整',
        ];

        return $typeMap[$type] ?? '其他';
    }

    /**
     * 修改密码
     * POST /api/user/change-password
     */
    public function changePassword()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $oldPassword = input('old_password', '');
        $newPassword = input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return error('请填写原密码和新密码');
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 20) {
            return error('新密码长度必须为6-20位');
        }

        $user = UserModel::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        // 验证原密码
        if (!password_verify($oldPassword, $user->password)) {
            return error('原密码错误');
        }

        // 更新密码
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        return success('密码修改成功');
    }

    /**
     * 绑定银行卡（统一接口）
     * POST /api/user/bind-bank-card
     */
    public function bindBankCard()
    {
        // 调用BankCard控制器的add方法
        $bankCard = new \app\api\controller\BankCard();
        return $bankCard->add();
    }

    /**
     * 绑定USDT地址（统一接口）
     * POST /api/user/bind-usdt-address
     */
    public function bindUsdtAddress()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $address = input('address', '');
        $network = input('network', 'TRC20');

        if (empty($address)) {
            return error('请输入USDT地址');
        }

        // 调用BankCard控制器的add方法（支持USDT地址）
        $bankCard = new \app\api\controller\BankCard();
        return $bankCard->add();
    }
}
