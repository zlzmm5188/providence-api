<?php
namespace app\api\controller;

use app\common\model\User as UserModel;
use app\common\model\RibaoLog;
use app\common\model\BalanceLog;
use think\facade\Db;

// 确保加载辅助函数
require_once dirname(__DIR__, 2) . '/common/helpers.php';

/**
 * 日利宝控制器
 *
 * 功能说明：
 * 1. 用户可以将账户余额（CNY或USDT）转入日利宝
 * 2. 日利宝按日计息，年化收益约0.73%（日利率0.002%）
 * 3. 用户可以随时转出日利宝余额
 * 4. 每日凌晨自动发放收益
 */
class Ribao
{
    /**
     * 获取日利宝头部信息
     * GET /api/user/ribaohead
     */
    public function head()
    {
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

        return success('获取成功', [
            // 日利宝余额
            'ribao_cny' => $user->ribao_cny ?? '0.00000000',
            'ribao_usdt' => $user->ribao_usdt ?? '0.00000000',

            // 账户可用余额
            'balance_cny' => $user->balance_cny ?? '0.00000000',
            'balance_usdt' => $user->balance_usdt ?? '0.00000000',

            // 日利率
            'daily_rate_cny' => '0.0020',  // 0.2% 日利率
            'daily_rate_usdt' => '0.0020', // 0.2% 日利率

            // 预估年化收益率
            'annual_rate' => '73',  // 73%

            // 今日已发放收益
            'today_profit_cny' => $this->getTodayProfit($userId, 'CNY'),
            'today_profit_usdt' => $this->getTodayProfit($userId, 'USDT'),

            // 累计收益
            'total_profit_cny' => $this->getTotalProfit($userId, 'CNY'),
            'total_profit_usdt' => $this->getTotalProfit($userId, 'USDT'),
        ]);
    }

    /**
     * 获取日利宝详细信息
     * GET /api/user/ribaoinfo
     */
    public function info()
    {
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

        return success('获取成功', [
            'ribao_cny' => $user->ribao_cny ?? '0.00000000',
            'ribao_usdt' => $user->ribao_usdt ?? '0.00000000',
            'balance_cny' => $user->balance_cny ?? '0.00000000',
            'balance_usdt' => $user->balance_usdt ?? '0.00000000',
            'daily_rate' => '0.0020',
            'annual_rate' => '73',
            'min_amount' => '100.00000000', // 最小转入金额
        ]);
    }

    /**
     * 获取日利宝记录
     * GET /api/user/ribaorecords
     */
    public function records()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 20);
        $type = input('type', ''); // transfer_in, transfer_out, profit
        $currency = input('currency', ''); // CNY, USDT

        $where = ['user_id' => $userId];

        if ($type) {
            $where['type'] = $type;
        }

        if ($currency) {
            $where['currency'] = $currency;
        }

        $list = RibaoLog::where($where)
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = RibaoLog::where($where)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 转入日利宝
     * POST /api/ribao/transfer_in
     */
    public function transfer_in()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $amount = input('amount', '0');
        $currency = input('currency', 'CNY'); // CNY 或 USDT
        $payPassword = input('pay_password', '');

        // 验证参数
        if (empty($amount) || bccomp($amount, '0', 8) <= 0) {
            return error('请输入正确的金额');
        }

        if (!in_array($currency, ['CNY', 'USDT'])) {
            return error('币种错误');
        }

        // 验证最小金额
        $minAmount = $currency === 'CNY' ? '100' : '10';
        if (bccomp($amount, $minAmount, 8) < 0) {
            return error("最小转入金额为 {$minAmount} {$currency}");
        }

        // 查询用户
        $user = UserModel::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        // 验证支付密码
        if (empty($payPassword)) {
            return error('请输入支付密码');
        }

        // 检查用户是否设置了支付密码
        if (empty($user->pay_password)) {
            return error('您尚未设置支付密码，请先设置支付密码');
        }

        if (!password_verify($payPassword, $user->pay_password)) {
            return error('支付密码错误');
        }

        // 检查余额
        $balanceField = $currency === 'CNY' ? 'balance_cny' : 'balance_usdt';
        $ribaoField = $currency === 'CNY' ? 'ribao_cny' : 'ribao_usdt';

        if (bccomp($user->$balanceField, $amount, 8) < 0) {
            return error('余额不足');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 扣除账户余额
            $user->$balanceField = bcsub($user->$balanceField, $amount, 8);

            // 增加日利宝余额
            $user->$ribaoField = bcadd($user->$ribaoField, $amount, 8);

            $user->save();

            // 记录日利宝日志
            RibaoLog::create([
                'user_id' => $userId,
                'type' => 'transfer_in',
                'currency' => $currency,
                'amount' => $amount,
                'before_balance' => bcadd($user->$balanceField, $amount, 8), // 转入前的账户余额
                'after_balance' => $user->$balanceField, // 转入后的账户余额
                'before_ribao' => bcsub($user->$ribaoField, $amount, 8), // 转入前的日利宝余额
                'after_ribao' => $user->$ribaoField, // 转入后的日利宝余额
                'remark' => '转入日利宝',
                'create_time' => time(),
            ]);

            // 记录余额变动日志
            BalanceLog::create([
                'user_id' => $userId,
                'type' => 'ribao_transfer_in',
                'currency' => $currency,
                'amount' => '-' . $amount,
                'before' => bcadd($user->$balanceField, $amount, 8),
                'after' => $user->$balanceField,
                'remark' => '转入日利宝',
                'create_time' => time(),
            ]);

            Db::commit();

            return success('转入成功', [
                'balance' => $user->$balanceField,
                'ribao' => $user->$ribaoField,
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return error('转入失败：' . $e->getMessage());
        }
    }

    /**
     * 转出日利宝
     * POST /api/ribao/transfer_out
     */
    public function transfer_out()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $amount = input('amount', '0');
        $currency = input('currency', 'CNY'); // CNY 或 USDT
        $payPassword = input('pay_password', '');

        // 验证参数
        if (empty($amount) || bccomp($amount, '0', 8) <= 0) {
            return error('请输入正确的金额');
        }

        if (!in_array($currency, ['CNY', 'USDT'])) {
            return error('币种错误');
        }

        // 查询用户
        $user = UserModel::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        // 验证支付密码
        if (empty($payPassword)) {
            return error('请输入支付密码');
        }

        // 检查用户是否设置了支付密码
        if (empty($user->pay_password)) {
            return error('您尚未设置支付密码，请先设置支付密码');
        }

        if (!password_verify($payPassword, $user->pay_password)) {
            return error('支付密码错误');
        }

        // 检查日利宝余额
        $balanceField = $currency === 'CNY' ? 'balance_cny' : 'balance_usdt';
        $ribaoField = $currency === 'CNY' ? 'ribao_cny' : 'ribao_usdt';

        if (bccomp($user->$ribaoField, $amount, 8) < 0) {
            return error('日利宝余额不足');
        }

        // 开启事务
        Db::startTrans();
        try {
            // 扣除日利宝余额
            $user->$ribaoField = bcsub($user->$ribaoField, $amount, 8);

            // 增加账户余额
            $user->$balanceField = bcadd($user->$balanceField, $amount, 8);

            $user->save();

            // 记录日利宝日志
            RibaoLog::create([
                'user_id' => $userId,
                'type' => 'transfer_out',
                'currency' => $currency,
                'amount' => $amount,
                'before_balance' => bcsub($user->$balanceField, $amount, 8), // 转出前的账户余额
                'after_balance' => $user->$balanceField, // 转出后的账户余额
                'before_ribao' => bcadd($user->$ribaoField, $amount, 8), // 转出前的日利宝余额
                'after_ribao' => $user->$ribaoField, // 转出后的日利宝余额
                'remark' => '转出日利宝',
                'create_time' => time(),
            ]);

            // 记录余额变动日志
            BalanceLog::create([
                'user_id' => $userId,
                'type' => 'ribao_transfer_out',
                'currency' => $currency,
                'amount' => '+' . $amount,
                'before' => bcsub($user->$balanceField, $amount, 8),
                'after' => $user->$balanceField,
                'remark' => '转出日利宝',
                'create_time' => time(),
            ]);

            Db::commit();

            return success('转出成功', [
                'balance' => $user->$balanceField,
                'ribao' => $user->$ribaoField,
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return error('转出失败：' . $e->getMessage());
        }
    }

    /**
     * 发放日利宝收益（定时任务调用）
     * 每日凌晨执行
     */
    public function distributeProfit()
    {
        // 验证调用来源（可以通过特殊token或IP白名单）
        $cronToken = input('cron_token', '');
        if ($cronToken !== config('app.cron_token', 'your_secret_token')) {
            return error('无权限');
        }

        $date = date('Y-m-d');
        $successCount = 0;
        $failCount = 0;
        $totalProfitCny = '0.00000000';
        $totalProfitUsdt = '0.00000000';

        // 日利率 0.2%
        $dailyRate = '0.0020';

        // 查询所有有日利宝余额的用户
        $users = UserModel::where(function($query) {
            $query->where('ribao_cny', '>', 0)
                  ->whereOr('ribao_usdt', '>', 0);
        })->select();

        foreach ($users as $user) {
            Db::startTrans();
            try {
                // 计算CNY收益
                if (bccomp($user->ribao_cny, '0', 8) > 0) {
                    $profitCny = bcmul($user->ribao_cny, $dailyRate, 8);

                    // 发放收益到账户余额
                    $user->balance_cny = bcadd($user->balance_cny, $profitCny, 8);
                    $totalProfitCny = bcadd($totalProfitCny, $profitCny, 8);

                    // 记录收益日志
                    RibaoLog::create([
                        'user_id' => $user->id,
                        'type' => 'profit',
                        'currency' => 'CNY',
                        'amount' => $profitCny,
                        'before_balance' => bcsub($user->balance_cny, $profitCny, 8),
                        'after_balance' => $user->balance_cny,
                        'before_ribao' => $user->ribao_cny,
                        'after_ribao' => $user->ribao_cny,
                        'remark' => "日利宝收益（{$date}）",
                        'create_time' => time(),
                    ]);

                    // 记录余额变动
                    BalanceLog::create([
                        'user_id' => $user->id,
                        'type' => 'ribao_profit',
                        'currency' => 'CNY',
                        'amount' => '+' . $profitCny,
                        'before' => bcsub($user->balance_cny, $profitCny, 8),
                        'after' => $user->balance_cny,
                        'remark' => "日利宝收益（{$date}）",
                        'create_time' => time(),
                    ]);
                }

                // 计算USDT收益
                if (bccomp($user->ribao_usdt, '0', 8) > 0) {
                    $profitUsdt = bcmul($user->ribao_usdt, $dailyRate, 8);

                    // 发放收益到账户余额
                    $user->balance_usdt = bcadd($user->balance_usdt, $profitUsdt, 8);
                    $totalProfitUsdt = bcadd($totalProfitUsdt, $profitUsdt, 8);

                    // 记录收益日志
                    RibaoLog::create([
                        'user_id' => $user->id,
                        'type' => 'profit',
                        'currency' => 'USDT',
                        'amount' => $profitUsdt,
                        'before_balance' => bcsub($user->balance_usdt, $profitUsdt, 8),
                        'after_balance' => $user->balance_usdt,
                        'before_ribao' => $user->ribao_usdt,
                        'after_ribao' => $user->ribao_usdt,
                        'remark' => "日利宝收益（{$date}）",
                        'create_time' => time(),
                    ]);

                    // 记录余额变动
                    BalanceLog::create([
                        'user_id' => $user->id,
                        'type' => 'ribao_profit',
                        'currency' => 'USDT',
                        'amount' => '+' . $profitUsdt,
                        'before' => bcsub($user->balance_usdt, $profitUsdt, 8),
                        'after' => $user->balance_usdt,
                        'remark' => "日利宝收益（{$date}）",
                        'create_time' => time(),
                    ]);
                }

                $user->save();
                Db::commit();
                $successCount++;

            } catch (\Exception $e) {
                Db::rollback();
                $failCount++;
                trace('日利宝收益发放失败 - 用户ID:' . $user->id . ' - 错误:' . $e->getMessage(), 'error');
            }
        }

        return success('收益发放完成', [
            'date' => $date,
            'total_users' => count($users),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_profit_cny' => $totalProfitCny,
            'total_profit_usdt' => $totalProfitUsdt,
        ]);
    }

    /**
     * 获取今日收益
     */
    private function getTodayProfit($userId, $currency)
    {
        $startTime = strtotime(date('Y-m-d 00:00:00'));
        $endTime = strtotime(date('Y-m-d 23:59:59'));

        $profit = RibaoLog::where('user_id', $userId)
            ->where('type', 'profit')
            ->where('currency', $currency)
            ->where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->sum('amount');

        return $profit ?: '0.00000000';
    }

    /**
     * 获取累计收益
     */
    private function getTotalProfit($userId, $currency)
    {
        $profit = RibaoLog::where('user_id', $userId)
            ->where('type', 'profit')
            ->where('currency', $currency)
            ->sum('amount');

        return $profit ?: '0.00000000';
    }
}
