<?php
namespace app\api\controller;

use app\common\model\User;
use app\common\model\WithdrawOrder;
use app\common\model\SystemConfig;
use app\common\service\TelegramBotService;
use think\facade\Db;

class Withdraw
{
    public function create()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $currency = input('currency');
        $amount = input('amount');
        $withdrawType = input('withdraw_type'); // bank_card/usdt
        $withdrawAccount = input('withdraw_account');
        $withdrawName = input('withdraw_name', '');
        $bankName = input('bank_name', '');

        Db::startTrans();
        try {
            $user = User::where('id', $userId)->lock(true)->find(); // 修复: user_id → id

            // 检查余额
            $field = ($currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
            $balance = $user->$field;

            if (bcmath_comp($balance, $amount) === -1) {
                throw new \Exception('余额不足');
            }

            // 计算手续费
            $feeKey = ($currency === 'CNY') ? 'withdraw_fee_cny' : 'withdraw_fee_usdt';
            $fee = SystemConfig::where('key', $feeKey)->value('value') ?: '0';
            $actualAmount = bcmath_sub($amount, $fee);

            if (bcmath_comp($actualAmount, '0') <= 0) {
                throw new \Exception('提现金额必须大于手续费');
            }

            // 冻结余额
            $frozenField = ($currency === 'CNY') ? 'frozen_cny' : 'frozen_usdt';
            $user->$field = bcmath_sub($user->$field, $amount);
            $user->$frozenField = bcmath_add($user->$frozenField, $amount);
            $user->save();

            // 创建订单
            $orderNo = generate_order_no('W');
            $order = WithdrawOrder::create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'currency' => $currency,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'withdraw_type' => $withdrawType,
                'withdraw_account' => $withdrawAccount,
                'withdraw_name' => $withdrawName,
                'bank_name' => $bankName,
                'status' => 0
            ]);

            Db::commit();

            // 发送Telegram通知
            TelegramBotService::notifyWithdraw([
                'username' => $user->username,
                'uid' => $user->uid
            ], [
                'amount' => $amount,
                'currency' => $currency,
                'withdraw_address' => $withdrawAccount,
                'order_no' => $orderNo,
                'ip' => request()->ip()
            ]);

            return success('提现申请已提交', [
                'order_id' => $order->id,
                'order_no' => $orderNo,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'status' => 0
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }

    public function list()
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

        $list = WithdrawOrder::where('user_id', $userId)
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = WithdrawOrder::where('user_id', $userId)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }
}
