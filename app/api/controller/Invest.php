<?php
namespace app\api\controller;

use app\common\model\User;
use app\common\model\InvestProject;
use app\common\model\InvestOrder;
use app\common\model\VipLevel;
use app\common\model\UserBankCard;
use app\common\model\UserUsdtAddress;
use app\common\model\WalletLog;
use app\common\service\VipService;
use app\common\service\ReferralService;
use app\common\service\TelegramBotService;
use think\facade\Db;

class Invest
{
    /**
     * 投资
     */
    public function create()
    {
        $userId = request()->userId;
        $projectId = input('project_id');
        $amount = input('amount');
        $currency = input('currency');
        $isTrial = input('is_trial', 0);

        Db::startTrans();
        try {
            // 1. 锁定用户
            $user = User::where('id', $userId)->lock(true)->find();

            // 2. 锁定项目
            $project = InvestProject::where('project_id', $projectId)
                ->where('status', 1)
                ->lock(true)
                ->find();

            if (!$project) {
                throw new \Exception('项目不存在或已下架');
            }

            // 3. 验证币种
            if ($project->currency !== $currency) {
                throw new \Exception('币种不匹配');
            }

            // 4. 验证金额范围
            if (bcmath_comp($amount, $project->min_amount) === -1 ||
                bcmath_comp($amount, $project->max_amount) === 1) {
                throw new \Exception('投资金额不在范围内');
            }

            // 5. 验证项目额度
            $remain = bcmath_sub($project->total_amount, $project->sold_amount);
            if (bcmath_comp($amount, $remain) === 1) {
                throw new \Exception('项目额度不足');
            }

            // 6. 验证VIP限制
            if ($project->vip_limit > 0 && $user->vip_level < $project->vip_limit) {
                throw new \Exception('VIP等级不足');
            }

            // 7. 验证前置条件（非体验金）
            if (!$isTrial) {
                if (!$user->is_kyc) {
                    throw new \Exception('请先完成实名认证');
                }

                // 检查收款方式
                if ($currency === 'CNY') {
                    $hasBank = UserBankCard::where('user_id', $userId)->count() > 0;
                    if (!$hasBank) {
                        throw new \Exception('请先绑定银行卡');
                    }
                } else {
                    $hasUsdt = UserUsdtAddress::where('user_id', $userId)->count() > 0;
                    if (!$hasUsdt) {
                        throw new \Exception('请先绑定USDT地址');
                    }
                }
            }

            // 8. 扣除余额
            $balanceField = $isTrial ? 'trial_balance' : ($currency === 'CNY' ? 'balance_cny' : 'balance_usdt');
            $oldBalance = $user->$balanceField;

            if (bcmath_comp($oldBalance, $amount) === -1) {
                throw new \Exception('余额不足');
            }

            $newBalance = bcmath_sub($oldBalance, $amount);
            $user->$balanceField = $newBalance;

            // 9. 累加total_invest（体验金不计入）
            if (!$isTrial) {
                $user->total_invest = bcmath_add($user->total_invest, $amount);
            }

            $user->save();

            // 10. 计算实际收益率
            $vipLevel = VipLevel::where('level', $user->vip_level)->find();
            $vipBonus = $vipLevel ? $vipLevel->interest_bonus : 0;
            $usdtBonus = ($currency === 'USDT') ? $project->usdt_bonus : 0;
            $finalRate = bcmath_add(bcmath_add($project->rate, $vipBonus, 6), $usdtBonus, 6);

            // 11. 计算收益
            $dailyProfit = bcmath_mul($amount, $finalRate);
            $totalProfit = bcmath_mul($dailyProfit, $project->cycle);

            // 12. 创建订单
            $orderNo = generate_order_no('I');
            $order = InvestOrder::create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'project_id' => $projectId,
                'project_name' => $project->name,
                'currency' => $currency,
                'amount' => $amount,
                'rate' => $finalRate,
                'cycle' => $project->cycle,
                'daily_profit' => $dailyProfit,
                'total_profit' => $totalProfit,
                'is_trial' => $isTrial,
                'status' => 1,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+' . $project->cycle . ' days'))
            ]);

            // 13. 更新项目已售
            $project->sold_amount = bcmath_add($project->sold_amount, $amount);
            $project->save();

            // 14. 记录钱包流水
            WalletLog::create([
                'user_id' => $userId,
                'currency' => $currency,
                'type' => $isTrial ? 'trial_invest' : 'invest',
                'amount' => '-' . $amount,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'related_id' => $order->id,
                'remark' => "投资项目：{$project->name}"
            ]);

            // 15. 体验金标记已投资
            if ($isTrial) {
                \app\common\model\TrialFund::where('user_id', $userId)
                    ->where('status', 1)
                    ->update([
                        'status' => 2,
                        'invested_at' => date('Y-m-d H:i:s')
                    ]);
            }

            // 16. 检查VIP升级（非体验金）
            if (!$isTrial) {
                VipService::checkAndUpgrade($userId);
            }

            Db::commit();

            // 17. 发送Telegram通知
            TelegramBotService::notifyInvest([
                'username' => $user->username,
                'uid' => $user->uid
            ], [
                'amount' => $amount,
                'currency' => $currency,
                'ip' => request()->ip()
            ], [
                'name' => $project->name,
                'return_rate' => $project->return_rate,
                'duration' => $project->duration
            ]);

            // 注意：推荐返利在投资结束时发放，不在投资时发放

            return success('投资成功', [
                'order_id' => $order->id,  // 返回主键id作为order_id
                'amount' => $amount,
                'rate' => $finalRate,
                'daily_profit' => $dailyProfit,
                'total_profit' => $totalProfit,
                'end_time' => $order->end_date
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }

    /**
     * 我的订单
     */
    public function orders()
    {
        $userId = request()->userId;
        $status = input('status', 0);
        $page = input('page', 1);
        $limit = input('limit', 10);

        $where = [['user_id', '=', $userId]];

        if ($status > 0) {
            $where[] = ['status', '=', $status];
        }

        $list = InvestOrder::where($where)
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = InvestOrder::where($where)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }

    /**
     * 订单结算（投资结束时调用）
     * 发放收益和推荐返利
     */
    public function settle()
    {
        $userId = request()->userId;
        $orderId = input('order_id');

        if (!$orderId) {
            return error('订单ID不能为空');
        }

        Db::startTrans();
        try {
            // 1. 锁定订单（order_id参数实际是主键id）
            $order = InvestOrder::where('id', $orderId)
                ->where('user_id', $userId)
                ->where('status', 1) // 只有运行中的订单才能结算
                ->lock(true)
                ->find();

            if (!$order) {
                throw new \Exception('订单不存在或已结算');
            }

            // 2. 检查订单是否到期
            $now = time();
            $endTime = strtotime($order->end_date);
            if ($now < $endTime) {
                throw new \Exception('订单尚未到期，无法结算');
            }

            // 3. 锁定用户
            $user = User::where('id', $userId)->lock(true)->find();
            if (!$user) {
                throw new \Exception('用户不存在');
            }

            // 4. 计算应发放金额（本金 + 收益）
            $amount = $order->amount;
            $totalProfit = $order->total_profit;
            $totalAmount = bcmath_add($amount, $totalProfit);

            // 5. 发放到用户余额
            $currency = $order->currency;
            $balanceField = $currency === 'CNY' ? 'balance_cny' : 'balance_usdt';
            $oldBalance = $user->$balanceField ?? '0';
            $newBalance = bcmath_add($oldBalance, $totalAmount);
            $user->$balanceField = $newBalance;
            $user->save();

            // 6. 更新订单状态
            $order->status = 2; // 2 = 已完成
            $order->settled_at = date('Y-m-d H:i:s');
            $order->save();

            // 7. 记录钱包流水
            WalletLog::create([
                'user_id' => $userId,
                'currency' => $currency,
                'type' => 'invest_settle',
                'amount' => $totalAmount,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'related_id' => $orderId,
                'remark' => "投资结算：本金{$amount} + 收益{$totalProfit}"
            ]);

            Db::commit();

            // 8. 发放推荐返利（事务外，包括体验金投资）
            try {
                ReferralService::processReward($orderId, $userId, $amount, $currency);
            } catch (\Exception $e) {
                // 返利失败不影响结算，记录日志
                \think\facade\Log::error('推荐返利发放失败: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'user_id' => $userId
                ]);
            }

            return success('结算成功', [
                'order_id' => $orderId,
                'amount' => $amount,
                'profit' => $totalProfit,
                'total' => $totalAmount
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }
}
