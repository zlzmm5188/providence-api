<?php
namespace app\api\controller;

use app\common\model\User;
use app\common\model\PointsLog;

class Points
{
    /**
     * 获取积分余额
     */
    public function balance()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return error('用户不存在');
        }

        return success('获取成功', [
            'points' => $user->points,
            'total_earned' => 0, // 可以从统计表获取
            'total_used' => 0,
        ]);
    }

    /**
     * 获取积分日志
     */
    public function logs()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 10);
        $type = input('type', ''); // earn/use

        $query = PointsLog::where('user_id', $userId);

        if ($type) {
            $query->where('type', $type);
        }

        $list = $query->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = $query->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }

    /**
     * 积分兑换
     */
    public function exchange()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $points = input('points');
        $currency = input('currency', 'CNY');
        $payPassword = input('pay_password', '');

        if ($points <= 0) {
            return error('兑换积分必须大于0');
        }

        // 验证支付密码
        if (empty($payPassword)) {
            return error('请输入支付密码');
        }

        $user = User::where('id', $userId)->lock(true)->find();

        if (empty($user->pay_password)) {
            return error('您尚未设置支付密码，请先设置支付密码');
        }

        if (!password_verify($payPassword, $user->pay_password)) {
            return error('支付密码错误');
        }

        if ($user->points < $points) {
            return error('积分不足');
        }

        // 兑换比例: 1积分 = 1元
        $exchangeRate = 1;
        $amount = bcmath_div((string)$points, (string)$exchangeRate, 8);

        // 扣除积分
        $user->points = $user->points - $points;

        // 增加余额
        $balanceField = ($currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
        $user->$balanceField = bcmath_add($user->$balanceField, $amount);

        $user->save();

        // 记录日志
        PointsLog::create([
            'user_id' => $userId,
            'type' => 'use',
            'points' => -$points,
            'remark' => "兑换{$amount}{$currency}",
            'balance_after' => $user->points
        ]);

        return success('兑换成功', [
            'points_used' => $points,
            'amount' => $amount,
            'currency' => $currency,
            'points_balance' => $user->points
        ]);
    }
}
