<?php
namespace app\api\controller;

use app\common\model\TrialFund as TrialFundModel;
use app\common\model\User;
use app\common\model\InvestOrder;
use think\facade\Db;

class TrialFund
{
    /**
     * 领取体验金
     * POST /api/trial-fund/claim
     */
    public function claim()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        // 检查是否已实名认证
        $user = User::find($userId);
        if (!$user) {
            return error('用户不存在');
        }

        if (($user->realname_status ?? 0) != 2) {
            return error('请先完成实名认证后才能领取体验金');
        }

        // 检查是否已领取过
        $existing = TrialFundModel::where('user_id', $userId)
            ->where('status', 'in', [1, 2]) // 1=已领取未投资, 2=已投资
            ->find();

        if ($existing) {
            return error('您已领取过体验金');
        }

        // 检查是否按身份证已领取（防止重复领取）
        if (!empty($user->id_card)) {
            $idCardClaimed = TrialFundModel::alias('tf')
                ->join('users u', 'tf.user_id = u.id')
                ->where('u.id_card', $user->id_card)
                ->where('tf.status', 'in', [1, 2])
                ->find();

            if ($idCardClaimed) {
                return error('该身份证已领取过体验金，每人限领一次');
            }
        }

        // 获取体验金配置
        $config = Db::name('system_config')
            ->where('key', 'trial_fund_amount')
            ->value('value');

        $amount = $config ?: '888'; // 默认888元

        Db::startTrans();
        try {
            // 创建体验金记录
            $trialFund = TrialFundModel::create([
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => 'CNY',
                'status' => 1, // 1=已领取未投资
                'expire_at' => date('Y-m-d H:i:s', strtotime('+7 days')), // 7天后过期
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 发放到用户余额
            $user->trial_balance = bcmath_add($user->trial_balance ?? '0', $amount);
            $user->save();

            Db::commit();

            return success('体验金领取成功', [
                'amount' => $amount,
                'expire_at' => $trialFund->expire_at
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return error('领取失败：' . $e->getMessage());
        }
    }

    /**
     * 获取体验金状态
     * GET /api/trial-fund/status
     */
    public function status()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $trialFund = TrialFundModel::where('user_id', $userId)
            ->order('id', 'desc')
            ->find();

        if (!$trialFund) {
            return success('获取成功', [
                'has_claimed' => false,
                'status' => 0,
                'amount' => 0
            ]);
        }

        return success('获取成功', [
            'has_claimed' => true,
            'status' => $trialFund->status, // 1=已领取未投资, 2=已投资, 3=已过期
            'amount' => $trialFund->amount,
            'expire_at' => $trialFund->expire_at,
            'invested_at' => $trialFund->invested_at ?? null
        ]);
    }

    /**
     * 获取体验金订单
     * GET /api/trial-fund/orders
     */
    public function orders()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 10);

        $list = InvestOrder::where('user_id', $userId)
            ->where('is_trial', 1)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = InvestOrder::where('user_id', $userId)
            ->where('is_trial', 1)
            ->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }
}
