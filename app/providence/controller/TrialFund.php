<?php
/**
 * 体验金管理控制器
 */

namespace app\providence\controller;

use app\common\model\TrialFund as TrialFundModel;
use app\common\model\User;
use think\facade\Db;

class TrialFund
{
    /**
     * 获取体验金记录列表
     * GET /providence/trial-funds
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
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

        $list = TrialFundModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            $user = User::find($item->user_id);
            $item->username = $user ? $user->username : '';
        }

        $total = TrialFundModel::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 手动回收体验金
     * POST /providence/trial-funds/:id/recover
     */
    public function recover()
    {
        $id = input('param.id', 0, 'intval');

        $trialFund = TrialFundModel::find($id);
        if (!$trialFund) {
            return json(['code' => -1, 'msg' => '体验金记录不存在', 'data' => null]);
        }

        if ($trialFund->status == 2) {
            return json(['code' => -1, 'msg' => '该体验金已回收', 'data' => null]);
        }

        try {
            Db::startTrans();

            $trialFund->status = 2;
            $trialFund->recovered_at = date('Y-m-d H:i:s');
            $trialFund->save();

            // TODO: 执行回收逻辑（扣除用户余额等）

            Db::commit();

            return success('回收成功');
        } catch (\Exception $e) {
            Db::rollback();
            return error('回收失败：' . $e->getMessage());
        }
    }

    /**
     * 获取体验金统计
     * GET /providence/trial-funds/statistics
     */
    public function statistics()
    {
        $total = TrialFundModel::count();
        $activeCount = TrialFundModel::where('status', 1)->count();
        $expiredCount = TrialFundModel::where('status', 3)->count();
        $recoveredCount = TrialFundModel::where('status', 2)->count();
        $totalIssued = TrialFundModel::sum('amount');
        $totalProfit = TrialFundModel::sum('profit');
        $totalRecovered = TrialFundModel::where('status', 2)->sum('amount');

        $statistics = [
            'total' => $total,
            'activeCount' => $activeCount,
            'expiredCount' => $expiredCount,
            'recoveredCount' => $recoveredCount,
            'totalIssued' => (float)$totalIssued,
            'totalProfit' => (float)$totalProfit,
            'totalRecovered' => (float)$totalRecovered,
            'usageRate' => $total > 0 ? round(($activeCount / $total) * 100, 2) : 0,
            'profitRate' => $totalIssued > 0 ? round(($totalProfit / $totalIssued) * 100, 2) : 0,
            'avgProfit' => $total > 0 ? round($totalProfit / $total, 2) : 0
        ];

        return success('获取成功', $statistics);
    }

    /**
     * 获取体验金配置
     * GET /providence/trial-funds/config
     */
    public function getConfig()
    {
        $config = Db::name('system_config')
            ->where('key', 'trial_fund_config')
            ->value('value');

        if ($config) {
            $config = json_decode($config, true);
        } else {
            $config = [
                'amount' => 888,
                'validDays' => 7,
                'autoRecover' => true,
                'recoverOnExpire' => true,
                'profitWithdrawable' => true,
                'allowedProjects' => []
            ];
        }

        return success('获取成功', $config);
    }

    /**
     * 更新体验金配置
     * POST /providence/trial-funds/config
     */
    public function updateConfig()
    {
        $config = input('post.');

        try {
            Db::name('system_config')
                ->where('key', 'trial_fund_config')
                ->updateOrInsert(
                    ['key' => 'trial_fund_config'],
                    ['value' => json_encode($config), 'updated_at' => date('Y-m-d H:i:s')]
                );

            return success('更新成功');
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage());
        }
    }
}
