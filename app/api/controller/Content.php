<?php
namespace app\api\controller;

use think\facade\Db;

class Content
{
    /**
     * 获取公告列表（用户端）
     * GET /api/announcements
     * 只返回已启用(status=1)的公告
     */
    public function announcements()
    {
        $page = input('page', 1, 'intval');
        $pageSize = input('pageSize', 20, 'intval');

        // 只查询已启用的公告
        $where = [
            ['status', '=', 1]
        ];

        $list = Db::name('announcement')
            ->where($where)
            ->order('is_important', 'desc') // 重要公告优先
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 格式化数据
        foreach ($list as &$item) {
            $item['is_important'] = (bool)($item['is_important'] ?? 0);
            $item['status'] = (bool)($item['status'] ?? 0);
        }

        $total = Db::name('announcement')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取活动弹窗
     * GET /api/activities/popup
     * 返回当前需要显示的活动弹窗（启用状态 + 在时间范围内 + show_popup=1）
     */
    public function popup()
    {
        $now = date('Y-m-d H:i:s');

        // 查询符合条件的活动弹窗
        // 条件：status=1（启用） + show_popup=1（显示弹窗） + 在时间范围内
        $activity = Db::name('activity')
            ->where('status', 1)
            ->where('show_popup', 1)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->order('id', 'desc')
            ->find();

        if (!$activity) {
            return success('获取成功', [
                'has_popup' => false,
                'popup' => null
            ]);
        }

        // 格式化数据
        $popup = [
            'id' => $activity['id'],
            'title' => $activity['title'],
            'description' => $activity['description'] ?? '',
            'image' => $activity['image'] ?? '',
            'type' => $activity['type'] ?? 'general',
            'reward_type' => $activity['reward_type'] ?? 'points',
            'reward_amount' => (float)($activity['reward_amount'] ?? 0),
            'start_time' => $activity['start_time'],
            'end_time' => $activity['end_time'],
            'condition' => $activity['condition'] ?? ''
        ];

        return success('获取成功', [
            'has_popup' => true,
            'popup' => $popup
        ]);
    }

    /**
     * 参与活动
     * POST /api/activities/:id/participate
     */
    public function participate($id)
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        // 查询活动
        $activity = Db::name('activity')->where('id', $id)->find();
        if (!$activity) {
            return error('活动不存在');
        }

        // 检查活动状态
        if ($activity['status'] != 1) {
            return error('活动未启用');
        }

        // 检查活动时间
        $now = date('Y-m-d H:i:s');
        if ($now < $activity['start_time'] || $now > $activity['end_time']) {
            return error('活动不在有效期内');
        }

        // 检查是否已参与（如果表存在）
        try {
            $participated = Db::name('activity_participants')
                ->where('activity_id', $id)
                ->where('user_id', $userId)
                ->find();

            if ($participated) {
                return error('您已参与过此活动');
            }
        } catch (\Exception $e) {
            // 如果表不存在，跳过检查（表会在首次使用时创建）
        }

        Db::startTrans();
        try {
            // 记录参与（如果表存在）
            try {
                Db::name('activity_participants')->insert([
                    'activity_id' => $id,
                    'user_id' => $userId,
                    'participated_at' => $now,
                    'created_at' => $now
                ]);
            } catch (\Exception $e) {
                // 如果表不存在，记录日志但不影响流程
                \think\facade\Log::warning('activity_participants表不存在，跳过参与记录: ' . $e->getMessage());
            }

            // 更新活动参与人数
            Db::name('activity')
                ->where('id', $id)
                ->inc('participants')
                ->update();

            // 发放奖励（如果有）
            $rewardType = $activity['reward_type'] ?? 'points';
            $rewardAmount = $activity['reward_amount'] ?? 0;

            if ($rewardAmount > 0) {
                if ($rewardType === 'points') {
                    // 发放积分
                    $user = Db::name('users')->where('id', $userId)->find();
                    if ($user) {
                        $newPoints = bcmath_add($user['points'] ?? '0', (string)$rewardAmount);
                        Db::name('users')
                            ->where('id', $userId)
                            ->update(['points' => $newPoints]);

                        // 记录积分流水
                        Db::name('points_logs')->insert([
                            'user_id' => $userId,
                            'type' => 'activity_reward',
                            'amount' => $rewardAmount,
                            'balance_before' => $user['points'] ?? '0',
                            'balance_after' => $newPoints,
                            'remark' => "活动奖励：{$activity['title']}",
                            'created_at' => $now
                        ]);

                        // 更新活动总奖励
                        Db::name('activity')
                            ->where('id', $id)
                            ->inc('total_reward', $rewardAmount)
                            ->update();
                    }
                } elseif ($rewardType === 'balance_cny') {
                    // 发放CNY余额
                    $user = Db::name('users')->where('id', $userId)->find();
                    if ($user) {
                        $newBalance = bcmath_add($user['balance_cny'] ?? '0', (string)$rewardAmount);
                        Db::name('users')
                            ->where('id', $userId)
                            ->update(['balance_cny' => $newBalance]);

                        // 记录钱包流水
                        Db::name('wallet_logs')->insert([
                            'user_id' => $userId,
                            'type' => 'activity_reward',
                            'currency' => 'CNY',
                            'amount' => $rewardAmount,
                            'balance_before' => $user['balance_cny'] ?? '0',
                            'balance_after' => $newBalance,
                            'remark' => "活动奖励：{$activity['title']}",
                            'created_at' => $now
                        ]);

                        // 更新活动总奖励
                        Db::name('activity')
                            ->where('id', $id)
                            ->inc('total_reward', $rewardAmount)
                            ->update();
                    }
                } elseif ($rewardType === 'balance_usdt') {
                    // 发放USDT余额
                    $user = Db::name('users')->where('id', $userId)->find();
                    if ($user) {
                        $newBalance = bcmath_add($user['balance_usdt'] ?? '0', (string)$rewardAmount);
                        Db::name('users')
                            ->where('id', $userId)
                            ->update(['balance_usdt' => $newBalance]);

                        // 记录钱包流水
                        Db::name('wallet_logs')->insert([
                            'user_id' => $userId,
                            'type' => 'activity_reward',
                            'currency' => 'USDT',
                            'amount' => $rewardAmount,
                            'balance_before' => $user['balance_usdt'] ?? '0',
                            'balance_after' => $newBalance,
                            'remark' => "活动奖励：{$activity['title']}",
                            'created_at' => $now
                        ]);

                        // 更新活动总奖励
                        Db::name('activity')
                            ->where('id', $id)
                            ->inc('total_reward', $rewardAmount)
                            ->update();
                    }
                }
            }

            Db::commit();

            return success('参与成功', [
                'activity_id' => $id,
                'reward_type' => $rewardType,
                'reward_amount' => $rewardAmount
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return error('参与失败：' . $e->getMessage());
        }
    }
}
