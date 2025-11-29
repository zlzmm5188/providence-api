<?php
namespace app\common\service;

use app\common\model\User;
use app\common\model\VipLevel;
use app\common\model\ReferralReward;
use app\common\model\WalletLog;

class ReferralService
{
    /**
     * 发放推荐返利
     */
    public static function processReward($orderId, $userId, $amount, $currency)
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        // 一级返利
        if ($user->parent_id) {
            // parent_id 存储的是父用户的 user_id（业务字段）
            $parent = User::where('id', $user->parent_id)->lock(true)->find();
            if (!$parent) {
                return false;
            }

            $vipLevel = VipLevel::where('level', $parent->vip_level)->find();
            if (!$vipLevel) {
                return false;
            }

            $rate1 = $vipLevel->referral_rate_1 ?? '0';
            // 数据库存储的是百分比（如1.0000表示1%），需要除以100
            $rate1Decimal = bcmath_div($rate1, '100', 8);
            $reward1 = bcmath_mul($amount, $rate1Decimal);

            // 发放到余额
            $field = ($currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
            $oldBalance = $parent->$field ?? '0';
            $newBalance = bcmath_add($oldBalance, $reward1);
            $parent->$field = $newBalance;
            $parent->save();

            // 记录返利
            ReferralReward::create([
                'user_id' => $parent->id,
                'from_user_id' => $userId,
                'order_id' => $orderId,
                'level' => 1,
                'currency' => $currency,
                'order_amount' => $amount,
                'rate' => $rate1,
                'reward_amount' => $reward1
            ]);

            // 记录流水
            WalletLog::create([
                'user_id' => $parent->id,
                'currency' => $currency,
                'type' => 'referral',
                'amount' => $reward1,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'related_id' => $orderId,
                'remark' => "推荐返利：一级下级投资"
            ]);

            // 二级返利
            if ($parent->parent_id) {
                $grandParent = User::where('id', $parent->parent_id)->lock(true)->find();
                if ($grandParent) {
                    $grandVipLevel = VipLevel::where('level', $grandParent->vip_level)->find();
                    if ($grandVipLevel) {
                        $rate2 = $grandVipLevel->referral_rate_2 ?? '0';

                        if (bcmath_comp($rate2, '0') > 0) {
                            // 数据库存储的是百分比（如0.5000表示0.5%），需要除以100
                            $rate2Decimal = bcmath_div($rate2, '100', 8);
                            $reward2 = bcmath_mul($amount, $rate2Decimal);

                            $oldBalance2 = $grandParent->$field ?? '0';
                            $newBalance2 = bcmath_add($oldBalance2, $reward2);
                            $grandParent->$field = $newBalance2;
                            $grandParent->save();

                            ReferralReward::create([
                                'user_id' => $grandParent->id,
                                'from_user_id' => $userId,
                                'order_id' => $orderId,
                                'level' => 2,
                                'currency' => $currency,
                                'order_amount' => $amount,
                                'rate' => $rate2,
                                'reward_amount' => $reward2
                            ]);

                            WalletLog::create([
                                'user_id' => $grandParent->id,
                                'currency' => $currency,
                                'type' => 'referral',
                                'amount' => $reward2,
                                'balance_before' => $oldBalance2,
                                'balance_after' => $newBalance2,
                                'related_id' => $orderId,
                                'remark' => "推荐返利：二级下级投资"
                            ]);
                        }
                    }
                }
            }
        }

        return true;
    }
}
