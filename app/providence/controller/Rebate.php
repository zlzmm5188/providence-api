<?php
/**
 * 返利管理控制器
 */

namespace app\providence\controller;

use app\common\model\User;
use think\facade\Db;

class Rebate
{
    /**
     * 获取返利统计数据
     * GET /providence/rebate/stats
     * 返回字段必须与前端期望完全一致
     */
    public function stats()
    {
        try {
            // 使用文档中的表名：referral_rewards
            // 如果表不存在，返回默认值，避免500错误

            // 今日发放返利（从referral_rewards表统计）
            $todayRebate = Db::name('referral_rewards')
                ->whereTime('created_at', 'today')
                ->sum('reward_amount') ?: 0;

            // 本月发放返利
            $monthRebate = Db::name('referral_rewards')
                ->whereTime('created_at', 'month')
                ->sum('reward_amount') ?: 0;

            // 累计发放返利
            $totalRebate = Db::name('referral_rewards')
                ->sum('reward_amount') ?: 0;

            // 返利用户数（有返利记录的用户）
            $totalUsers = Db::name('referral_rewards')
                ->group('user_id')
                ->count('DISTINCT user_id');

            // 一级返利总额
            $level1Total = Db::name('referral_rewards')
                ->where('level', 1)
                ->sum('reward_amount') ?: 0;

            // 二级返利总额
            $level2Total = Db::name('referral_rewards')
                ->where('level', 2)
                ->sum('reward_amount') ?: 0;

            // 团队奖励总额（从team_rewards表统计，转换为CNY金额）
            // 注意：team_rewards表中reward_amount是积分，需要根据配置转换为CNY
            // 这里假设1积分=1CNY，实际应根据系统配置调整
            $teamTotal = Db::name('team_rewards')
                ->where('status', 1)
                ->sum('reward_amount') ?: 0;

            return success('获取成功', [
                'today_rebate' => number_format((float)$todayRebate, 2, '.', ''),
                'month_rebate' => number_format((float)$monthRebate, 2, '.', ''),
                'total_rebate' => number_format((float)$totalRebate, 2, '.', ''),
                'total_users' => (int)$totalUsers,
                'level1_total' => number_format((float)$level1Total, 2, '.', ''),
                'level2_total' => number_format((float)$level2Total, 2, '.', ''),
                'team_total' => number_format((float)$teamTotal, 2, '.', '')
            ]);
        } catch (\Exception $e) {
            // 如果表不存在或查询失败，返回默认值，避免500错误
            return success('获取成功', [
                'today_rebate' => '0.00',
                'month_rebate' => '0.00',
                'total_rebate' => '0.00',
                'total_users' => 0,
                'level1_total' => '0.00',
                'level2_total' => '0.00',
                'team_total' => '0.00'
            ]);
        }
    }

    /**
     * 获取返利记录列表
     * GET /providence/rebate/list
     */
    public function list()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $level = input('get.level', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        // 搜索关键词
        if (!empty($keyword)) {
            $userIds = User::where('username', 'like', "%{$keyword}%")->column('id');
            if (!empty($userIds)) {
                $where[] = ['user_id', 'in', $userIds];
            } else {
                return success('获取成功', ['list' => [], 'total' => 0]);
            }
        }

        // 级别筛选
        if ($level !== '') {
            $where[] = ['level', '=', intval($level)];
        }

        // 时间筛选
        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        try {
            // 使用文档中的表名：referral_rewards
            $list = Db::name('referral_rewards')
                ->where($where)
                ->order('id', 'desc')
                ->page($page, $pageSize)
                ->select();

            // 关联用户信息
            foreach ($list as &$item) {
                $user = User::find($item['user_id']);
                $item['username'] = $user ? $user->username : '';

                // 关联下级用户信息
                if (!empty($item['from_user_id'])) {
                    $fromUser = User::find($item['from_user_id']);
                    $item['from_username'] = $fromUser ? $fromUser->username : '';
                }
            }

            $total = Db::name('referral_rewards')->where($where)->count();

            return success('获取成功', [
                'list' => $list,
                'total' => (int)$total
            ]);
        } catch (\Exception $e) {
            // 如果表不存在或查询失败，返回空列表，避免500错误
            return success('获取成功', [
                'list' => [],
                'total' => 0
            ]);
        }
    }
}
