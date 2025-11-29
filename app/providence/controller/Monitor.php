<?php
/**
 * 系统监控控制器（审计日志、登录日志、风险控制）
 */

namespace app\providence\controller;

use app\common\model\AuditLog as AuditLogModel;
use app\common\model\User;
use think\facade\Db;

class Monitor
{
    /**
     * 获取审计日志列表
     * GET /providence/audit-logs
     */
    public function auditLogs()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $module = input('get.module', '', 'trim');
        $action = input('get.action', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['admin_name', 'like', "%{$keyword}%"];
        }

        if (!empty($module)) {
            $where[] = ['module', '=', $module];
        }

        if (!empty($action)) {
            $where[] = ['action', '=', $action];
        }

        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $list = AuditLogModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = AuditLogModel::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取登录日志列表
     * GET /providence/login-logs
     */
    public function loginLogs()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');
        $userType = input('get.user_type', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $userIds = User::where('username', 'like', "%{$keyword}%")->column('id');
            if (!empty($userIds)) {
                $where[] = ['user_id', 'in', $userIds];
            } else {
                return success('获取成功', ['list' => [], 'total' => 0]);
            }
        }

        if ($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }

        if (!empty($userType)) {
            $where[] = ['user_type', '=', $userType];
        }

        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $list = Db::name('login_log')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            if ($item['user_id']) {
                $user = User::find($item['user_id']);
                $item['username'] = $user ? $user->username : '';
            }
        }

        $total = Db::name('login_log')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取风险警报列表
     * GET /providence/risk/alerts
     */
    public function riskAlerts()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $level = input('get.level', '', 'trim');
        $type = input('get.type', '', 'trim');
        $status = input('get.status', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['message', 'like', "%{$keyword}%"];
        }

        if (!empty($level)) {
            $where[] = ['level', '=', $level];
        }

        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        if ($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }

        $list = Db::name('risk_alert')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            if ($item['user_id']) {
                $user = User::find($item['user_id']);
                $item['username'] = $user ? $user->username : '';
            }
        }

        $total = Db::name('risk_alert')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取黑名单列表
     * GET /providence/risk/blacklist
     */
    public function riskBlacklist()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $type = input('get.type', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['value', 'like', "%{$keyword}%"];
        }

        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        $list = Db::name('risk_blacklist')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = Db::name('risk_blacklist')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 添加黑名单
     * POST /providence/risk/blacklist
     */
    public function addBlacklist()
    {
        $type = input('post.type', '', 'trim');
        $value = input('post.value', '', 'trim');
        $reason = input('post.reason', '', 'trim');

        if (empty($type) || empty($value)) {
            return error('类型和值不能为空');
        }

        try {
            Db::name('risk_blacklist')->insert([
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return success('添加成功');
        } catch (\Exception $e) {
            return error('添加失败：' . $e->getMessage());
        }
    }

    /**
     * 获取风控规则
     * GET /providence/risk/rules
     */
    public function riskRules()
    {
        $rules = Db::name('risk_rule')
            ->order('id', 'asc')
            ->select();

        return success('获取成功', $rules);
    }

    /**
     * 更新风控规则
     * POST /providence/risk/rules
     */
    public function updateRiskRules()
    {
        $rules = input('post.');

        try {
            Db::name('risk_rule')
                ->where('key', 'risk_rules')
                ->updateOrInsert(
                    ['key' => 'risk_rules'],
                    ['value' => json_encode($rules), 'updated_at' => date('Y-m-d H:i:s')]
                );

            return success('更新成功');
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 获取风险统计数据
     * GET /providence/risk/stats
     */
    public function riskStats()
    {
        try {
            $warningCount = Db::name('risk_alert')->where('level', 'warning')->count();
            $blacklistCount = Db::name('risk_blacklist')->count();
            $highRiskUsers = Db::name('risk_alert')
                ->where('level', 'high')
                ->group('user_id')
                ->count();
            $todayAlerts = Db::name('risk_alert')
                ->whereTime('created_at', 'today')
                ->count();
        } catch (\Exception $e) {
            return success('获取成功', [
                'warning_count' => 0,
                'blacklist_count' => 0,
                'high_risk_users' => 0,
                'today_alerts' => 0
            ]);
        }

        return success('获取成功', [
            'warning_count' => $warningCount,
            'blacklist_count' => $blacklistCount,
            'high_risk_users' => $highRiskUsers,
            'today_alerts' => $todayAlerts
        ]);
    }

    /**
     * 处理风险警报
     * POST /providence/risk/alerts/:id/handle
     */
    public function handleAlert()
    {
        $id = input('param.id', 0, 'intval');
        $action = input('post.action', '', 'trim'); // ignore/resolve/block

        if (!in_array($action, ['ignore', 'resolve', 'block'])) {
            return error('操作类型错误');
        }

        return $this->processAlert($id, $action);
    }

    /**
     * 解决风险警报
     * POST /providence/risk/alerts/:id/resolve
     */
    public function resolveAlert()
    {
        $id = input('param.id', 0, 'intval');
        return $this->processAlert($id, 'resolve');
    }

    /**
     * 忽略风险警报
     * POST /providence/risk/alerts/:id/ignore
     */
    public function ignoreAlert()
    {
        $id = input('param.id', 0, 'intval');
        return $this->processAlert($id, 'ignore');
    }

    /**
     * 处理警报的通用方法
     */
    private function processAlert($id, $action)
    {
        if (!$id) {
            return error('警报ID不能为空');
        }

        try {
            $alert = Db::name('risk_alert')->where('id', $id)->find();
            if (!$alert) {
                return error('警报不存在');
            }

            $adminId = request()->userId ?? 0;
            $updateData = [];

            if ($action === 'resolve') {
                // 标记为已解决
                $updateData['status'] = 1; // 1=已解决
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
                $updateData['resolved_by'] = $adminId;
            } elseif ($action === 'ignore') {
                // 标记为已忽略
                $updateData['status'] = 2; // 2=已忽略
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
                $updateData['resolved_by'] = $adminId;
            } elseif ($action === 'block') {
                // 加入黑名单
                $updateData['status'] = 1;
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
                $updateData['resolved_by'] = $adminId;

                // 添加到黑名单
                if ($alert['user_id']) {
                    $user = User::find($alert['user_id']);
                    if ($user) {
                        Db::name('risk_blacklist')->insert([
                            'type' => 'user_id',
                            'value' => (string)$alert['user_id'],
                            'reason' => '风险警报自动加入',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            if (!empty($updateData)) {
                Db::name('risk_alert')->where('id', $id)->update($updateData);
            }

            return success('操作成功');
        } catch (\Exception $e) {
            return error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 处理风险警报（旧方法，保持兼容）
     */
    public function handleAlertOld()
    {
        $id = input('param.id', 0, 'intval');
        $action = input('post.action', '', 'trim'); // ignore/resolve/block

        if (!in_array($action, ['ignore', 'resolve', 'block'])) {
            return error('操作类型错误');
        }

        try {
            $alert = Db::name('risk_alert')->where('id', $id)->find();
            if (!$alert) {
                return error('警报不存在');
            }

            $updateData = [
                'status' => $action === 'ignore' ? 2 : ($action === 'resolve' ? 1 : 3),
                'handled_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            Db::name('risk_alert')->where('id', $id)->update($updateData);

            // 如果是block操作，添加到黑名单
            if ($action === 'block' && $alert['user_id']) {
                $user = User::find($alert['user_id']);
                if ($user) {
                    Db::name('risk_blacklist')->insert([
                        'type' => 'user_id',
                        'value' => $user->id,
                        'reason' => '风险警报自动封禁',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            return success('处理成功');
        } catch (\Exception $e) {
            return error('处理失败：' . $e->getMessage());
        }
    }

    /**
     * 移除黑名单
     * POST /providence/risk/blacklist/remove
     */
    public function removeBlacklist()
    {
        $id = input('post.id', 0, 'intval');

        if (empty($id)) {
            return error('ID不能为空');
        }

        try {
            Db::name('risk_blacklist')->where('id', $id)->delete();
            return success('移除成功');
        } catch (\Exception $e) {
            return error('移除失败：' . $e->getMessage());
        }
    }
}
