<?php
/**
 * 团队管理控制器（后台）
 */

namespace app\providence\controller;

use app\common\model\User;
use think\facade\Db;

class Team
{
    /**
     * 获取团队奖励记录列表
     * GET /providence/team/rewards
     */
    public function rewards()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $level = input('get.level', '', 'trim');
        $status = input('get.status', '', 'trim');
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
            $where[] = ['reward_level', '=', intval($level)];
        }

        // 状态筛选
        if ($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }

        // 时间筛选
        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $list = Db::name('team_reward_log')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息并计算统计数据
        foreach ($list as &$item) {
            $user = User::find($item['user_id']);
            $item['username'] = $user ? $user->username : '';
            $item['uid'] = (string)$item['user_id'];

            // 获取团队成员数和总投资额
            $teamStats = $this->getTeamStats($item['user_id']);
            $item['member_count'] = $teamStats['members'];
            $item['total_invest'] = $teamStats['total_investment'];
            $item['level'] = $item['reward_level'];
            $item['reward_amount'] = $item['reward_points'];
        }

        $total = Db::name('team_reward_log')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取团队规则
     * GET /providence/team/rules
     */
    public function getRules()
    {
        $rules = Db::name('team_reward')
            ->order('level', 'asc')
            ->select();

        // 转换status为boolean
        foreach ($rules as &$rule) {
            $rule['status'] = (bool)$rule['status'];
        }

        return success('获取成功', $rules);
    }

    /**
     * 更新团队规则
     * POST /providence/team/rules
     */
    public function updateRules()
    {
        $rules = input('post.rules', []);

        if (empty($rules) || !is_array($rules)) {
            return error('规则数据无效');
        }

        try {
            Db::startTrans();

            foreach ($rules as $rule) {
                Db::name('team_reward')
                    ->where('level', $rule['level'])
                    ->updateOrInsert(
                        ['level' => $rule['level']],
                        [
                            'member_count' => $rule['member_count'],
                            'total_invest' => $rule['total_invest'],
                            'reward_amount' => $rule['reward_amount'],
                            'status' => $rule['status'] ? 1 : 0,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    );
            }

            Db::commit();

            return success('更新成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 获取团队统计数据
     */
    private function getTeamStats($userId)
    {
        $directMembers = User::where('parent_id', $userId)->column('id');
        $allTeamMembers = $directMembers;

        // 递归获取所有下级成员
        $queue = $directMembers;
        while (!empty($queue)) {
            $currentMemberId = array_shift($queue);
            $subMembers = User::where('parent_id', $currentMemberId)->column('id');
            $allTeamMembers = array_merge($allTeamMembers, $subMembers);
            $queue = array_merge($queue, $subMembers);
        }
        $allTeamMembers = array_unique($allTeamMembers);

        $totalInvestment = 0;
        if (!empty($allTeamMembers)) {
            $totalInvestment = Db::name('invest_order')
                ->whereIn('user_id', $allTeamMembers)
                ->sum('amount');
        }

        return [
            'members' => count($allTeamMembers),
            'total_investment' => (float)$totalInvestment
        ];
    }
}
