<?php
/**
 * 系统配置管理控制器
 */

namespace app\providence\controller;

use app\common\model\VipLevel;
use think\facade\Db;

class System
{
    /**
     * 获取系统配置
     * GET /providence/system/config
     */
    public function getConfig()
    {
        $config = Db::name('system_config')
            ->where('key', 'system_config')
            ->value('value');

        if ($config) {
            $config = json_decode($config, true);
        } else {
            $config = [
                'basic' => [
                    'siteName' => 'Providence',
                    'logo' => '',
                    'customerService' => '',
                    'minRecharge' => 100,
                    'minWithdraw' => 100,
                    'registerBonus' => 0
                ],
                'reward' => [
                    'dailyCheckIn' => 10,
                    'referralTiming' => 'immediate',
                    'referralWithdrawable' => true,
                    'trialFundReferral' => false
                ]
            ];
        }

        // 获取VIP配置
        $vipConfig = VipLevel::order('level', 'asc')->select();
        $config['vip'] = $vipConfig->toArray();

        // 获取团队奖励配置
        $teamConfig = Db::name('team_reward')->order('level', 'asc')->select();
        $config['team'] = $teamConfig;

        return success('获取成功', $config);
    }

    /**
     * 更新系统配置
     * POST /providence/system/config
     */
    public function updateConfig()
    {
        $config = input('post.');

        try {
            Db::name('system_config')
                ->where('key', 'system_config')
                ->updateOrInsert(
                    ['key' => 'system_config'],
                    ['value' => json_encode($config), 'updated_at' => date('Y-m-d H:i:s')]
                );

            return success('更新成功');
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 获取VIP配置
     * GET /providence/system/vip-config
     */
    public function getVipConfig()
    {
        $vipConfig = VipLevel::order('level', 'asc')->select();

        // 统一字段名称，兼容前端
        $result = [];
        foreach ($vipConfig as $vip) {
            $result[] = [
                'level' => $vip['level'],
                'required_amount' => $vip['required_amount'] ?? $vip['required_invest'] ?? 0,
                'interest_bonus' => $vip['interest_bonus'] ?? $vip['interest_rate'] ?? 0,
                'referral_rate_1' => $vip['referral_rate_1'] ?? 0,
                'referral_rate_2' => $vip['referral_rate_2'] ?? 0,
                'checkin_points' => $vip['checkin_points'] ?? 0,
            ];
        }

        return success('获取成功', $result);
    }

    /**
     * 更新VIP配置
     * POST /providence/system/vip-config
     */
    public function updateVipConfig()
    {
        $vipConfig = input('post.vip', []);

        if (empty($vipConfig) || !is_array($vipConfig)) {
            return error('VIP配置数据无效');
        }

        try {
            Db::startTrans();

            foreach ($vipConfig as $vip) {
                $updateData = [
                    'required_amount' => $vip['required_amount'] ?? $vip['required_invest'] ?? 0,
                    'interest_bonus' => $vip['interest_bonus'] ?? $vip['interest_rate'] ?? 0,
                    'referral_rate_1' => $vip['referral_rate_1'] ?? 0,
                    'referral_rate_2' => $vip['referral_rate_2'] ?? 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // 如果存在签到积分字段，也更新
                if (isset($vip['checkin_points'])) {
                    $updateData['checkin_points'] = $vip['checkin_points'];
                }

                VipLevel::where('level', $vip['level'])
                    ->updateOrInsert(
                        ['level' => $vip['level']],
                        $updateData
                    );
            }

            Db::commit();

            return json([
                'code' => 1,
                'msg' => '更新成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '更新失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取团队奖励配置
     * GET /providence/system/team-reward-config
     */
    public function getTeamRewardConfig()
    {
        $teamConfig = Db::name('team_reward')->order('level', 'asc')->select();

        return success('获取成功', $teamConfig);
    }

    /**
     * 更新团队奖励配置
     * POST /providence/system/team-reward-config
     */
    public function updateTeamRewardConfig()
    {
        $teamConfig = input('post.team', []);

        if (empty($teamConfig) || !is_array($teamConfig)) {
            return error('团队配置数据无效');
        }

        try {
            Db::startTrans();

            foreach ($teamConfig as $team) {
                Db::name('team_reward')
                    ->where('level', $team['level'])
                    ->updateOrInsert(
                        ['level' => $team['level']],
                        [
                            'member_count' => $team['member_count'],
                            'total_invest' => $team['total_invest'],
                            'reward_amount' => $team['reward_amount'],
                            'status' => $team['status'] ? 1 : 0,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    );
            }

            Db::commit();

            return json([
                'code' => 1,
                'msg' => '更新成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '更新失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 批量更新系统配置
     * POST /providence/system/config/batch
     */
    public function batchUpdateConfig()
    {
        $config = input('post.');

        try {
            Db::startTrans();

            foreach ($config as $key => $value) {
                Db::name('system_config')
                    ->where('key', $key)
                    ->updateOrInsert(
                        ['key' => $key],
                        ['value' => is_array($value) ? json_encode($value) : $value, 'updated_at' => date('Y-m-d H:i:s')]
                    );
            }

            Db::commit();

            return success('批量更新成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('批量更新失败：' . $e->getMessage());
        }
    }

    /**
     * 获取团队配置
     * GET /providence/system/team-config
     */
    public function getTeamConfig()
    {
        $teamConfig = Db::name('team_reward')->order('level', 'asc')->select();

        return success('获取成功', $teamConfig);
    }

    /**
     * 更新团队配置
     * POST /providence/system/team-config
     */
    public function updateTeamConfig()
    {
        $teamConfig = input('post.');

        try {
            Db::startTrans();

            if (isset($teamConfig['levels']) && is_array($teamConfig['levels'])) {
                foreach ($teamConfig['levels'] as $level) {
                    Db::name('team_reward')
                        ->where('level', $level['level'])
                        ->updateOrInsert(
                            ['level' => $level['level']],
                            [
                                'member_count' => $level['member_count'] ?? 0,
                                'total_invest' => $level['total_invest'] ?? 0,
                                'reward_amount' => $level['reward_amount'] ?? 0,
                                'status' => $level['status'] ?? 1,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]
                        );
                }
            }

            Db::commit();

            return success('更新成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('更新失败：' . $e->getMessage());
        }
    }
}
