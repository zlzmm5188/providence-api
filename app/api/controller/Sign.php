<?php
namespace app\api\controller;

use app\common\model\User;
use think\facade\Db;

class Sign
{
    /**
     * 获取签到信息
     * GET /api/user/sign/info
     */
    public function info()
    {
        try {
            // 如果前端没有传user_id，尝试从请求头获取
            $userId = request()->userId ?? input('user_id');

            // 如果没有登录，允许返回示例数据或提示登录
            // 暂时允许未登录查看
            if (!$userId) {
                return json([
                    'code' => 0,
                    'msg' => '成功',
                    'data' => [
                        'is_checkin' => false,
                        'continuous_days' => 0,
                        'total_checkins' => 0,
                        'today' => date('Y-m-d'),
                        'last_checkin_date' => null
                    ]
                ]);
            }

            // 查询用户签到数据
            $user = User::find($userId);
            if (!$user) {
                return json([
                    'code' => 0,
                    'msg' => '成功',
                    'data' => [
                        'is_checkin' => false,
                        'continuous_days' => 0,
                        'total_checkins' => 0,
                        'today' => date('Y-m-d'),
                        'last_checkin_date' => null
                    ]
                ]);
            }

            // 从sign_logs表查询今日签到状态
            $today = date('Y-m-d');
            $todaySignin = Db::table('sign_logs')
                ->where('user_id', $userId)
                ->where('sign_date', $today)
                ->find();

            // 查询连续签到天数
            $continuousDays = $this->calculateContinuousDays($userId);

            // 查询总签到次数
            $totalSignins = Db::table('sign_logs')
                ->where('user_id', $userId)
                ->count();

            // 查询最后一次签到
            $lastSignin = Db::table('sign_logs')
                ->where('user_id', $userId)
                ->order('sign_date', 'desc')
                ->find();

            return json([
                'code' => 0,
                'msg' => '成功',
                'data' => [
                    'is_checkin' => (bool)$todaySignin,
                    'continuous_days' => (int)$continuousDays,
                    'total_checkins' => (int)$totalSignins,
                    'today' => $today,
                    'last_checkin_date' => $lastSignin ? $lastSignin['sign_date'] : null
                ]
            ]);

        } catch (\Exception $e) {
            \think\facade\Log::error('获取签到信息失败: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return json([
                'code' => -1,
                'msg' => '获取签到信息失败',
                'data' => []
            ]);
        }
    }

    /**
     * 执行签到
     * POST /api/user/sign/sign
     */
    public function sign()
    {
        try {
            // 获取用户ID
            $userId = request()->userId ?? input('user_id');

            if (!$userId) {
                return json([
                    'code' => -1,
                    'msg' => '请先登录',
                    'data' => []
                ]);
            }

            $today = date('Y-m-d');

            // 检查今日是否已签到
            $existSign = Db::table('sign_logs')
                ->where('user_id', $userId)
                ->where('sign_date', $today)
                ->find();

            if ($existSign) {
                return json([
                    'code' => -1,
                    'msg' => '今日已签到',
                    'data' => []
                ]);
            }

            // 计算连续签到天数
            $continuousDays = $this->calculateContinuousDays($userId);

            // 签到奖励规则：随机10-30积分，连续签到7天额外奖励50积分
            $basePoints = rand(10, 30);
            $bonusPoints = 0;

            // 如果明天是第7天或更多的倍数，给予额外奖励
            if (($continuousDays + 1) % 7 == 0) {
                $bonusPoints = 50;
            }

            $totalPoints = $basePoints + $bonusPoints;

            // 开启事务
            Db::startTrans();

            try {
                // 1. 记录签到日志
                Db::table('sign_logs')->insert([
                    'user_id' => $userId,
                    'sign_date' => $today,
                    'points_reward' => $totalPoints,
                    'continuous_days' => $continuousDays + 1,
                    'bonus_flag' => $bonusPoints > 0 ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // 2. 更新用户积分
                $user = User::find($userId);
                $user->points = ($user->points ?? 0) + $totalPoints;
                $user->save();

                Db::commit();

                return json([
                    'code' => 1,
                    'msg' => '签到成功',
                    'data' => [
                        'points' => $totalPoints,
                        'bonus_points' => $bonusPoints,
                        'continuous_days' => $continuousDays + 1,
                        'total_points' => (float)($user->points ?? 0)
                    ]
                ]);

            } catch (\Exception $innerError) {
                Db::rollback();
                throw $innerError;
            }

        } catch (\Exception $e) {
            try {
                Db::rollback();
            } catch (\Exception $rollbackError) {
                // 忽略回滚错误
            }

            \think\facade\Log::error('签到失败: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId ?? null
            ]);

            return json([
                'code' => -1,
                'msg' => '签到失败，请稍后重试',
                'data' => []
            ]);
        }
    }

    /**
     * 计算连续签到天数
     */
    private function calculateContinuousDays($userId)
    {
        $logs = Db::table('sign_logs')
            ->where('user_id', $userId)
            ->order('sign_date', 'desc')
            ->select();

        if (empty($logs)) {
            return 0;
        }

        $continuous = 0;
        $today = new \DateTime(date('Y-m-d'));

        foreach ($logs as $log) {
            $logDate = new \DateTime($log['sign_date']);
            $diff = $today->diff($logDate)->days;

            // 如果相差1天或0天（今天），继续计算
            if ($diff == $continuous) {
                $continuous++;
                $today = $logDate;
            } else {
                break;
            }
        }

        return $continuous;
    }
}
