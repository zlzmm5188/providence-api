<?php
/**
 * 团队管理奖服务
 * 根据下级成员人数和累计投资额发放积分奖励
 */

namespace app\service;

use app\common\model\User;
use app\common\model\WalletLog;
use think\facade\Db;

class TeamRewardService
{
    /**
     * 团队管理奖级别配置
     * 格式：['member_count' => ['min_invest' => 金额, 'points' => 积分]]
     */
    private static $rewardLevels = [
        3 => ['min_invest' => 80000, 'points' => 1800],
        5 => ['min_invest' => 150000, 'points' => 2500],
        10 => ['min_invest' => 500000, 'points' => 8800],
        20 => ['min_invest' => 1500000, 'points' => 18000],
        50 => ['min_invest' => 3800000, 'points' => 25000],
        100 => ['min_invest' => 8800000, 'points' => 38000],
        200 => ['min_invest' => 15000000, 'points' => 66000],
        500 => ['min_invest' => 58000000, 'points' => 100000],
        1000 => ['min_invest' => 98000000, 'points' => 180000],
    ];

    /**
     * 检查并发放团队管理奖
     * @param int $userId 用户ID
     * @return array 返回奖励信息
     */
    public static function checkAndReward($userId)
    {
        try {
            // 获取用户的下级成员数和累计投资额
            $teamStats = self::getTeamStats($userId);

            $memberCount = $teamStats['member_count'];
            $totalInvest = $teamStats['total_invest'];

            // 查找符合条件的最高级别
            $matchedLevel = null;
            $matchedPoints = 0;

            foreach (self::$rewardLevels as $level => $config) {
                if ($memberCount >= $level && $totalInvest >= $config['min_invest']) {
                    $matchedLevel = $level;
                    $matchedPoints = $config['points'];
                }
            }

            if (!$matchedLevel) {
                return [
                    'code' => 0,
                    'msg' => '未达到任何奖励级别',
                    'data' => null
                ];
            }

            // 检查是否已经发放过该级别的奖励
            $user = User::find($userId);
            if (!$user) {
                return [
                    'code' => -1,
                    'msg' => '用户不存在',
                    'data' => null
                ];
            }

            $rewardedLevel = $user->team_reward_level ?? 0;
            if ($matchedLevel <= $rewardedLevel) {
                return [
                    'code' => 0,
                    'msg' => '该级别奖励已发放',
                    'data' => [
                        'level' => $matchedLevel,
                        'points' => 0,
                        'already_rewarded' => true
                    ]
                ];
            }

            // 发放积分奖励
            Db::startTrans();
            try {
                // 增加用户积分
                $user->points = bcadd($user->points ?? 0, $matchedPoints, 8);
                $user->team_reward_level = $matchedLevel;
                $user->save();

                // 记录积分变动日志
                WalletLog::create([
                    'user_id' => $userId,
                    'type' => 'team_reward',
                    'amount' => $matchedPoints,
                    'currency' => 'points',
                    'balance_before' => bcsub($user->points, $matchedPoints, 8),
                    'balance_after' => $user->points,
                    'remark' => "团队管理奖-{$matchedLevel}人级别",
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                Db::commit();

                return [
                    'code' => 1,
                    'msg' => '奖励发放成功',
                    'data' => [
                        'level' => $matchedLevel,
                        'points' => $matchedPoints,
                        'member_count' => $memberCount,
                        'total_invest' => $totalInvest
                    ]
                ];
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'code' => -1,
                'msg' => '发放奖励失败：' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 获取团队统计信息
     * @param int $userId 用户ID
     * @return array
     */
    private static function getTeamStats($userId)
    {
        // 获取所有下级成员（一级和二级）
        $directMembers = User::where('inviter_id', $userId)->column('id');
        $allMembers = $directMembers;

        // 获取二级成员
        if (!empty($directMembers)) {
            $secondLevel = User::where('inviter_id', 'in', $directMembers)->column('id');
            $allMembers = array_merge($allMembers, $secondLevel);
        }

        $memberCount = count($allMembers);

        // 计算累计投资额（所有下级成员的投资总额）
        $totalInvest = Db::name('invest_order')
            ->where('user_id', 'in', $allMembers)
            ->where('status', 'completed')
            ->sum('amount') ?? 0;

        return [
            'member_count' => $memberCount,
            'total_invest' => $totalInvest
        ];
    }

    /**
     * 获取奖励级别配置
     * @return array
     */
    public static function getRewardLevels()
    {
        return self::$rewardLevels;
    }

    /**
     * 获取用户当前奖励级别信息
     * @param int $userId 用户ID
     * @return array
     */
    public static function getUserRewardInfo($userId)
    {
        $teamStats = self::getTeamStats($userId);
        $user = User::find($userId);

        $currentLevel = $user->team_reward_level ?? 0;
        $nextLevel = null;
        $nextLevelConfig = null;

        // 查找下一个可达到的级别
        foreach (self::$rewardLevels as $level => $config) {
            if ($level > $currentLevel) {
                $nextLevel = $level;
                $nextLevelConfig = $config;
                break;
            }
        }

        return [
            'current_level' => $currentLevel,
            'current_points' => self::$rewardLevels[$currentLevel]['points'] ?? 0,
            'member_count' => $teamStats['member_count'],
            'total_invest' => $teamStats['total_invest'],
            'next_level' => $nextLevel,
            'next_level_config' => $nextLevelConfig,
            'all_levels' => self::$rewardLevels
        ];
    }
}
