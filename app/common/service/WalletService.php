<?php
namespace app\common\service;

use app\common\model\User;
use app\common\model\WalletLog;

class WalletService
{
    /**
     * 增加余额
     */
    public static function addBalance($userId, $amount, $currency, $type, $relatedId = null, $remark = '')
    {
        $user = User::where('id', $userId)->lock(true)->find();

        $field = ($currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
        $oldBalance = $user->$field;
        $newBalance = bcmath_add($oldBalance, $amount);

        $user->$field = $newBalance;
        $user->save();

        // 记录流水
        WalletLog::create([
            'user_id' => $userId,
            'currency' => $currency,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'related_id' => $relatedId,
            'remark' => $remark
        ]);

        return true;
    }

    /**
     * 扣除余额
     */
    public static function subBalance($userId, $amount, $currency, $type, $relatedId = null, $remark = '')
    {
        $user = User::where('id', $userId)->lock(true)->find();

        $field = ($currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
        $oldBalance = $user->$field;

        // 检查余额
        if (bcmath_comp($oldBalance, $amount) === -1) {
            throw new \Exception('余额不足');
        }

        $newBalance = bcmath_sub($oldBalance, $amount);

        $user->$field = $newBalance;
        $user->save();

        // 记录流水（负数）
        WalletLog::create([
            'user_id' => $userId,
            'currency' => $currency,
            'type' => $type,
            'amount' => '-' . $amount,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'related_id' => $relatedId,
            'remark' => $remark
        ]);

        return true;
    }
}
