<?php
/**
 * 团队控制器
 */

namespace app\api\controller;

use app\common\middleware\AuthMiddleware;
use app\service\TeamRewardService;
use think\facade\Db;

class Team
{
    /**
     * 获取团队成员列表
     * GET /api/team/members
     */
    public function members()
    {
        $userId = request()->userId;
        $page = input('get.page', 1, 'intval');
        $limit = input('get.limit', 20, 'intval');

        // 获取一级成员
        $directMembers = Db::name('user')
            ->where('inviter_id', $userId)
            ->field('id,username,created_at,total_invest,vip_level')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = Db::name('user')
            ->where('inviter_id', $userId)
            ->count();

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $directMembers,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * 获取团队奖励信息
     * GET /api/team/reward-info
     */
    public function rewardInfo()
    {
        $userId = request()->userId;

        $info = TeamRewardService::getUserRewardInfo($userId);

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => $info
        ]);
    }

    /**
     * 领取团队奖励
     * POST /api/team/claim-reward
     */
    public function claimReward()
    {
        $userId = request()->userId;

        $result = TeamRewardService::checkAndReward($userId);

        return json($result);
    }
}
