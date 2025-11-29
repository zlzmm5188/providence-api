<?php
namespace app\api\controller;

use app\common\model\User;
use app\common\model\UserRelation;

class Team
{
    public function members()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $level = input('level', 0); // 0=全部, 1=一级, 2=二级
        $page = input('page', 1);
        $limit = input('limit', 10);
        $onlyValid = input('only_valid', 0); // 是否只返回有效成员（实名+充值+购买）

        $where = [['parent_id', '=', $userId]];

        if ($level > 0) {
            $where[] = ['level', '=', $level];
        }

        $relations = UserRelation::where($where)
            ->page($page, $limit)
            ->select();

        $userIds = array_column($relations->toArray(), 'user_id');

        if (empty($userIds)) {
            return success('获取成功', [
                'total' => 0,
                'total_valid' => 0,
                'list' => []
            ]);
        }

        $users = User::whereIn('id', $userIds)->select();

        $list = [];
        $validCount = 0;

        foreach ($relations as $relation) {
            foreach ($users as $user) {
                if ($user->id == $relation->user_id) {
                    // 计算持仓（充值 - 提现）
                    $recharges = floatval($user->recharges ?? 0);
                    $withdraws = floatval($user->withdraws ?? 0);
                    $currentHolding = $recharges - $withdraws;

                    // 判断是否为有效成员：实名认证 + 充值 + 有持仓 + 有投资
                    $isKycVerified = ($user->is_kyc ?? 0) == 2 || ($user->is_kyc ?? 0) == 1;
                    $hasRecharged = $recharges > 0;
                    $hasHolding = $currentHolding > 0;
                    $hasInvested = floatval($user->total_invest ?? 0) > 0;
                    $isValid = $isKycVerified && $hasRecharged && $hasHolding && $hasInvested;

                    if ($isValid) {
                        $validCount++;
                    }

                    // 如果设置了只返回有效成员，则过滤
                    if ($onlyValid && !$isValid) {
                        continue;
                    }

                    $list[] = [
                        'user_id' => $user->id,
                        'uid' => $user->uid ?? '',
                        'username' => $user->username ?? '',
                        'realname' => $user->realname ?? '',
                        'vip_level' => intval($user->vip_level ?? 0),
                        'total_invest' => floatval($user->total_invest ?? 0),
                        'recharges' => $recharges,
                        'withdraws' => $withdraws,
                        'current_holding' => $currentHolding,
                        'is_kyc' => intval($user->is_kyc ?? 0),
                        'is_valid' => $isValid ? 1 : 0, // 是否有效成员
                        'level' => $relation->level,
                        'join_time' => $user->created_at ?? ''
                    ];
                }
            }
        }

        $total = UserRelation::where($where)->count();

        return success('获取成功', [
            'total' => $total,
            'total_valid' => $validCount,
            'list' => $list
        ]);
    }

    /**
     * 获取团队奖励信息
     * 只统计有效成员（实名+充值+购买）
     */
    public function rewardInfo()
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

        // 获取所有下级ID
        $level1Ids = UserRelation::where('parent_id', $userId)->where('level', 1)->column('user_id');
        $level2Ids = UserRelation::where('parent_id', $userId)->where('level', 2)->column('user_id');

        // 获取所有下级用户信息
        $allMemberIds = array_merge($level1Ids, $level2Ids);
        $allMembers = [];
        if (!empty($allMemberIds)) {
            $allMembers = User::whereIn('id', $allMemberIds)->select();
        }

        // 统计有效成员（实名+充值+购买）
        $validLevel1Ids = [];
        $validLevel2Ids = [];
        $totalInvest = 0;

        // 获取USDT汇率（用于将USDT投资转换为CNY）
        $usdtRate = 7.2; // 默认汇率
        try {
            $rateConfig = Db::name('system_config')->where('key', 'usdt_exchange_rate')->value('value');
            if ($rateConfig) {
                $usdtRate = floatval($rateConfig);
            }
        } catch (\Exception $e) {
            // 使用默认汇率
        }

        foreach ($allMembers as $member) {
            // 判断是否为有效成员
            $recharges = floatval($member->recharges ?? 0);
            $withdraws = floatval($member->withdraws ?? 0);
            $currentHolding = $recharges - $withdraws;
            $isKycVerified = ($member->is_kyc ?? 0) == 2;
            $hasRecharged = $recharges > 0;
            $hasHolding = $currentHolding > 0;
            $hasInvested = floatval($member->total_invest ?? 0) > 0;
            $isValid = $isKycVerified && $hasRecharged && $hasHolding && $hasInvested;

            if ($isValid) {
                if (in_array($member->id, $level1Ids)) {
                    $validLevel1Ids[] = $member->id;
                }
                if (in_array($member->id, $level2Ids)) {
                    $validLevel2Ids[] = $member->id;
                }

                // 从投资订单表统计该成员的投资（排除体验金，排除个人购买）
                // 只统计团队成员的投资，不统计当前用户自己的投资
                if ($member->id != $userId) {
                    $memberInvestOrders = Db::name('invest_orders')
                        ->where('user_id', $member->id)
                        ->where('is_trial', 0) // 排除体验金
                        ->where('status', 'in', [1, 2]) // 1=运行中, 2=已完成
                        ->select();

                    foreach ($memberInvestOrders as $order) {
                        $orderAmount = floatval($order['amount'] ?? 0);
                        $orderCurrency = $order['currency'] ?? 'CNY';

                        // 如果是USDT，按汇率转换为CNY
                        if ($orderCurrency === 'USDT') {
                            $orderAmount = $orderAmount * $usdtRate;
                        }

                        $totalInvest += $orderAmount;
                    }
                }
            }
        }

        $level1Count = count($validLevel1Ids);
        $level2Count = count($validLevel2Ids);
        $totalCount = $level1Count + $level2Count;

        return success('获取成功', [
            'team_count_1' => $level1Count,
            'team_count_2' => $level2Count,
            'team_count_total' => $totalCount,
            'team_invest_total' => $totalInvest,
            'total_reward' => '0.00000000', // 可以从奖励记录表获取
            'available_reward' => '0.00000000',
        ]);
    }

    /**
     * 领取团队奖励
     */
    public function claimReward()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        // 这里应该实现实际的奖励领取逻辑
        return error('暂无可领取的奖励');
    }

    /**
     * 获取团队信息
     * GET /api/team/info
     */
    public function info()
    {
        // 调用rewardInfo方法
        return $this->rewardInfo();
    }

    /**
     * 获取推荐返利记录
     * GET /api/team/referral-rewards
     */
    public function referralRewards()
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

        // 查询推荐返利记录（从钱包流水表或返利记录表）
        $list = \think\facade\Db::name('wallet_logs')
            ->where('user_id', $userId)
            ->where('type', 'referral_reward')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = \think\facade\Db::name('wallet_logs')
            ->where('user_id', $userId)
            ->where('type', 'referral_reward')
            ->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }
}
