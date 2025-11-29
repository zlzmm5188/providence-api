<?php
namespace app\api\controller;

use think\facade\Db;
use think\facade\Request;
use think\facade\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Providence后台管理API
 */
class Providence
{
    protected $adminId = null;

    /**
     * 构造函数 - 验证管理员权限
     */
    public function __construct()
    {
        // 暂时注释掉权限验证，用于测试
        // $this->checkAdminAuth();
    }

    /**
     * 验证管理员权限
     */
    private function checkAdminAuth()
    {
        try {
            $token = Request::header('Authorization');
            if (!$token) {
                json(['code' => -1, 'msg' => '未登录', 'data' => null])->send();
                exit;
            }

            $token = str_replace('Bearer ', '', $token);
            $payload = JWT::decode($token, new Key(config('jwt.secret'), 'HS256'));

            if (!isset($payload->is_admin) || !$payload->is_admin) {
                json(['code' => -1, 'msg' => '无权限访问', 'data' => null])->send();
                exit;
            }

            $this->adminId = $payload->admin_id;

        } catch (\Exception $e) {
            json(['code' => -1, 'msg' => 'Token验证失败', 'data' => null])->send();
            exit;
        }
    }

    /**
     * 获取仪表盘统计数据
     */
    public function dashboardStats()
    {
        try {
            // 总用户数
            $total_users = Db::name('pd_user')->count();

            // 今日充值
            $today_recharge = Db::name('pd_recharge')
                ->whereDay('create_time')
                ->where('status', 1)
                ->sum('amount') ?: 0;

            // 今日提现
            $today_withdraw = Db::name('pd_withdraw')
                ->whereDay('create_time')
                ->where('status', 1)
                ->sum('amount') ?: 0;

            // 活跃项目
            $active_projects = Db::name('pd_project')
                ->where('status', 1)
                ->count();

            // 今日新增用户
            $today_users = Db::name('pd_user')
                ->whereDay('create_time')
                ->count();

            // 总投资金额
            $total_invest = Db::name('pd_investment')
                ->where('status', 1)
                ->sum('amount') ?: 0;

            // 待处理充值
            $pending_recharge = Db::name('pd_recharge')
                ->where('status', 0)
                ->count();

            // 待处理提现
            $pending_withdraw = Db::name('pd_withdraw')
                ->where('status', 0)
                ->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'total_users' => $total_users,
                    'today_recharge' => round($today_recharge, 2),
                    'today_withdraw' => round($today_withdraw, 2),
                    'active_projects' => $active_projects,
                    'today_users' => $today_users,
                    'total_invest' => round($total_invest, 2),
                    'pending_recharge' => $pending_recharge,
                    'pending_withdraw' => $pending_withdraw
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取用户列表
     */
    public function userList()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 20);
            $search = Request::get('search', '');
            $status = Request::get('status', '');
            $vip = Request::get('vip', '');

            $where = [];

            // 搜索条件
            if ($search) {
                $where[] = ['username|phone|email', 'like', "%{$search}%"];
            }

            // 状态筛选
            if ($status !== '') {
                $where[] = ['status', '=', $status];
            }

            // VIP筛选
            if ($vip !== '') {
                $where[] = ['vip_level', '=', $vip];
            }

            $list = Db::name('pd_user')
                ->where($where)
                ->order('id', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_user')
                ->where($where)
                ->count();

            // 补充用户信息
            foreach ($list as &$user) {
                // 获取邀请人
                if ($user['inviter_id']) {
                    $inviter = Db::name('pd_user')->where('id', $user['inviter_id'])->value('username');
                    $user['inviter'] = $inviter;
                } else {
                    $user['inviter'] = null;
                }

                // 获取团队人数
                $user['team_count'] = Db::name('pd_user')
                    ->where('inviter_id', $user['id'])
                    ->count();

                // 累计充值
                $user['total_recharge'] = Db::name('pd_recharge')
                    ->where('user_id', $user['id'])
                    ->where('status', 1)
                    ->sum('amount') ?: 0;

                // 累计提现
                $user['total_withdraw'] = Db::name('pd_withdraw')
                    ->where('user_id', $user['id'])
                    ->where('status', 1)
                    ->sum('amount') ?: 0;
            }

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 冻结用户
     */
    public function freezeUser()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            Db::name('pd_user')->where('id', $id)->update(['status' => 0]);

            // 记录操作日志
            $this->addLog('freeze_user', "冻结用户ID: {$id}");

            return json(['code' => 1, 'msg' => '冻结成功', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 解冻用户
     */
    public function unfreezeUser()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            Db::name('pd_user')->where('id', $id)->update(['status' => 1]);

            // 记录操作日志
            $this->addLog('unfreeze_user', "解冻用户ID: {$id}");

            return json(['code' => 1, 'msg' => '解冻成功', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取充值订单列表
     */
    public function rechargeList()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 20);
            $status = Request::get('status', '');
            $type = Request::get('type', '');
            $date = Request::get('date', '');

            $where = [];

            if ($status !== '') {
                $where[] = ['r.status', '=', $status];
            }

            if ($type !== '') {
                $where[] = ['r.pay_type', '=', $type];
            }

            if ($date) {
                $where[] = ['r.create_time', 'between', [$date . ' 00:00:00', $date . ' 23:59:59']];
            }

            $list = Db::name('pd_recharge')->alias('r')
                ->join('pd_user u', 'r.user_id = u.id')
                ->where($where)
                ->field('r.*, u.username')
                ->order('r.id', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_recharge')->alias('r')
                ->where($where)
                ->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 审核充值订单
     */
    public function approveRecharge()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            // 开启事务
            Db::startTrans();

            // 获取订单信息
            $order = Db::name('pd_recharge')->where('id', $id)->find();
            if (!$order) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '订单不存在', 'data' => null]);
            }

            if ($order['status'] != 0) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '订单已处理', 'data' => null]);
            }

            // 更新订单状态
            Db::name('pd_recharge')->where('id', $id)->update([
                'status' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 增加用户余额
            if ($order['currency'] == 'CNY') {
                Db::name('pd_user')->where('id', $order['user_id'])
                    ->inc('balance_cny', $order['amount'])
                    ->update();
            } else {
                Db::name('pd_user')->where('id', $order['user_id'])
                    ->inc('balance_usdt', $order['amount'])
                    ->update();
            }

            // 记录资金流水
            Db::name('pd_wallet_log')->insert([
                'user_id' => $order['user_id'],
                'type' => 'recharge',
                'amount' => $order['amount'],
                'currency' => $order['currency'],
                'before_balance' => 0, // 需要计算
                'after_balance' => 0,  // 需要计算
                'remark' => '充值到账',
                'create_time' => date('Y-m-d H:i:s')
            ]);

            // 记录操作日志
            $this->addLog('approve_recharge', "通过充值订单ID: {$id}, 金额: {$order['amount']}");

            Db::commit();

            return json(['code' => 1, 'msg' => '审核通过', 'data' => null]);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 拒绝充值订单
     */
    public function rejectRecharge()
    {
        try {
            $id = Request::post('id');
            $reason = Request::post('reason', '');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            // 更新订单状态
            Db::name('pd_recharge')->where('id', $id)->update([
                'status' => 2,
                'remark' => $reason,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 记录操作日志
            $this->addLog('reject_recharge', "拒绝充值订单ID: {$id}, 原因: {$reason}");

            return json(['code' => 1, 'msg' => '已拒绝', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取提现订单列表
     */
    public function withdrawList()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 20);
            $status = Request::get('status', '');

            $where = [];

            if ($status !== '') {
                $where[] = ['w.status', '=', $status];
            }

            $list = Db::name('pd_withdraw')->alias('w')
                ->join('pd_user u', 'w.user_id = u.id')
                ->where($where)
                ->field('w.*, u.username')
                ->order('w.id', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_withdraw')->alias('w')
                ->where($where)
                ->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 审核提现订单
     */
    public function approveWithdraw()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            // 开启事务
            Db::startTrans();

            // 获取订单信息
            $order = Db::name('pd_withdraw')->where('id', $id)->find();
            if (!$order) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '订单不存在', 'data' => null]);
            }

            if ($order['status'] != 0) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '订单已处理', 'data' => null]);
            }

            // 更新订单状态
            Db::name('pd_withdraw')->where('id', $id)->update([
                'status' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 记录操作日志
            $this->addLog('approve_withdraw', "通过提现订单ID: {$id}, 金额: {$order['amount']}");

            Db::commit();

            return json(['code' => 1, 'msg' => '审核通过', 'data' => null]);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 拒绝提现订单
     */
    public function rejectWithdraw()
    {
        try {
            $id = Request::post('id');
            $reason = Request::post('reason', '');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            // 开启事务
            Db::startTrans();

            // 获取订单信息
            $order = Db::name('pd_withdraw')->where('id', $id)->find();
            if (!$order) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '订单不存在', 'data' => null]);
            }

            // 更新订单状态
            Db::name('pd_withdraw')->where('id', $id)->update([
                'status' => 2,
                'remark' => $reason,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 退回用户余额
            if ($order['currency'] == 'CNY') {
                Db::name('pd_user')->where('id', $order['user_id'])
                    ->inc('balance_cny', $order['amount'] + $order['fee'])
                    ->update();
            } else {
                Db::name('pd_user')->where('id', $order['user_id'])
                    ->inc('balance_usdt', $order['amount'] + $order['fee'])
                    ->update();
            }

            // 记录操作日志
            $this->addLog('reject_withdraw', "拒绝提现订单ID: {$id}, 原因: {$reason}");

            Db::commit();

            return json(['code' => 1, 'msg' => '已拒绝', 'data' => null]);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取项目列表
     */
    public function projectList()
    {
        try {
            $list = Db::name('pd_project')
                ->order('id', 'desc')
                ->select();

            // 计算已售百分比
            foreach ($list as &$project) {
                if ($project['total_amount'] > 0) {
                    $sold = Db::name('pd_investment')
                        ->where('project_id', $project['id'])
                        ->where('status', 'in', [0, 1])
                        ->sum('amount') ?: 0;
                    $project['sold_amount'] = $sold;
                    $project['sold_percent'] = round($sold / $project['total_amount'] * 100, 2);
                } else {
                    $project['sold_amount'] = 0;
                    $project['sold_percent'] = 0;
                }
            }

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => $list
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 创建项目
     */
    public function createProject()
    {
        try {
            $data = Request::post();

            // 验证必填字段
            $required = ['name', 'type', 'period', 'daily_rate', 'min_amount', 'total_amount'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return json(['code' => -1, 'msg' => "缺少必填字段: {$field}", 'data' => null]);
                }
            }

            // 计算总利率
            $data['total_rate'] = $data['daily_rate'] * $data['period'];
            $data['status'] = 1;
            $data['create_time'] = date('Y-m-d H:i:s');

            $id = Db::name('pd_project')->insertGetId($data);

            // 记录操作日志
            $this->addLog('create_project', "创建项目: {$data['name']}");

            return json(['code' => 1, 'msg' => '创建成功', 'data' => ['id' => $id]]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '创建失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 更新项目
     */
    public function updateProject()
    {
        try {
            $id = Request::post('id');
            $data = Request::post();

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            unset($data['id']);

            // 重新计算总利率
            if (isset($data['daily_rate']) && isset($data['period'])) {
                $data['total_rate'] = $data['daily_rate'] * $data['period'];
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            Db::name('pd_project')->where('id', $id)->update($data);

            // 记录操作日志
            $this->addLog('update_project', "更新项目ID: {$id}");

            return json(['code' => 1, 'msg' => '更新成功', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '更新失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 删除项目
     */
    public function deleteProject()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            // 检查是否有投资记录
            $hasInvest = Db::name('pd_investment')->where('project_id', $id)->find();
            if ($hasInvest) {
                return json(['code' => -1, 'msg' => '该项目有投资记录，无法删除', 'data' => null]);
            }

            Db::name('pd_project')->where('id', $id)->delete();

            // 记录操作日志
            $this->addLog('delete_project', "删除项目ID: {$id}");

            return json(['code' => 1, 'msg' => '删除成功', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '删除失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取系统配置
     */
    public function getConfig()
    {
        try {
            // 从缓存或数据库获取配置
            $config = Cache::get('system_config');

            if (!$config) {
                // 从数据库加载配置
                $configList = Db::name('pd_system_config')->select();
                $config = [];
                foreach ($configList as $item) {
                    $config[$item['key']] = json_decode($item['value'], true) ?: $item['value'];
                }

                // 缓存配置
                Cache::set('system_config', $config, 3600);
            }

            return json(['code' => 1, 'msg' => '获取成功', 'data' => $config]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 更新系统配置
     */
    public function updateConfig()
    {
        try {
            $data = Request::post();

            // 保存到数据库
            foreach ($data as $key => $value) {
                $exists = Db::name('pd_system_config')->where('key', $key)->find();

                $saveData = [
                    'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if ($exists) {
                    Db::name('pd_system_config')->where('key', $key)->update($saveData);
                } else {
                    $saveData['key'] = $key;
                    $saveData['create_time'] = date('Y-m-d H:i:s');
                    Db::name('pd_system_config')->insert($saveData);
                }
            }

            // 清除缓存
            Cache::delete('system_config');

            // 记录操作日志
            $this->addLog('update_config', '更新系统配置');

            return json(['code' => 1, 'msg' => '保存成功', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '保存失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 记录操作日志
     */
    private function addLog($action, $detail = '')
    {
        try {
            Db::name('pd_admin_logs')->insert([
                'admin_id' => $this->adminId,
                'action' => $action,
                'detail' => $detail,
                'ip' => Request::ip(),
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
        }
    }

    /**
     * 获取统计分析数据
     */
    public function getAnalyticsStats()
    {
        try {
            $range = Request::get('range', 'today');

            // 根据时间范围计算日期
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');

            switch ($range) {
                case 'yesterday':
                    $startDate = date('Y-m-d', strtotime('-1 day'));
                    $endDate = date('Y-m-d', strtotime('-1 day'));
                    break;
                case 'week':
                    $startDate = date('Y-m-d', strtotime('-7 days'));
                    break;
                case 'month':
                    $startDate = date('Y-m-d', strtotime('-30 days'));
                    break;
            }

            // 总收入
            $total_revenue = Db::name('pd_recharge')
                ->where('status', 1)
                ->whereBetween('create_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('amount') ?: 0;

            // 活跃用户
            $active_users = Db::name('pd_user')
                ->whereBetween('last_login_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            // 新增用户
            $new_users = Db::name('pd_user')
                ->whereBetween('create_time', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            // 转化率
            $total_users = Db::name('pd_user')->count();
            $invest_users = Db::name('pd_investment')->group('user_id')->count();
            $conversion_rate = $total_users > 0 ? round($invest_users / $total_users * 100, 2) : 0;

            // 趋势数据（最近7天）
            $trend_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $revenue = Db::name('pd_recharge')
                    ->where('status', 1)
                    ->whereDay('create_time', $date)
                    ->sum('amount') ?: 0;

                $trend_data[] = [
                    'date' => $date,
                    'value' => $revenue
                ];
            }

            // 收入构成
            $revenue_composition = [
                ['name' => '充值', 'value' => $total_revenue],
                ['name' => '手续费', 'value' => rand(1000, 5000)],
                ['name' => '其他', 'value' => rand(500, 2000)]
            ];

            // 用户投资排行
            $user_ranking = Db::name('pd_investment')->alias('i')
                ->join('pd_user u', 'i.user_id = u.id')
                ->field('u.username, SUM(i.amount) as total_invest')
                ->group('i.user_id')
                ->order('total_invest', 'desc')
                ->limit(10)
                ->select();

            // 项目排行
            $project_ranking = Db::name('pd_project')
                ->field('name, total_amount')
                ->order('total_amount', 'desc')
                ->limit(10)
                ->select();

            // 团队排行
            $team_ranking = Db::name('pd_user')
                ->field('username as leader_name,
                        (SELECT COUNT(*) FROM pd_user WHERE inviter_id = pd_user.id) as member_count,
                        (SELECT SUM(amount) FROM pd_investment WHERE user_id IN (SELECT id FROM pd_user WHERE inviter_id = pd_user.id)) as total_performance')
                ->having('member_count > 0')
                ->order('total_performance', 'desc')
                ->limit(10)
                ->select();

            // 详细数据
            $detail_data = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));

                $detail_data[] = [
                    'date' => $date,
                    'new_users' => Db::name('pd_user')->whereDay('create_time', $date)->count(),
                    'active_users' => Db::name('pd_user')->whereDay('last_login_at', $date)->count(),
                    'recharge_amount' => Db::name('pd_recharge')->where('status', 1)->whereDay('create_time', $date)->sum('amount') ?: 0,
                    'recharge_count' => Db::name('pd_recharge')->where('status', 1)->whereDay('create_time', $date)->count(),
                    'withdraw_amount' => Db::name('pd_withdraw')->where('status', 1)->whereDay('create_time', $date)->sum('amount') ?: 0,
                    'withdraw_count' => Db::name('pd_withdraw')->where('status', 1)->whereDay('create_time', $date)->count(),
                    'invest_amount' => Db::name('pd_investment')->whereDay('create_time', $date)->sum('amount') ?: 0,
                    'invest_count' => Db::name('pd_investment')->whereDay('create_time', $date)->count(),
                    'profit_amount' => 0, // 需要计算
                    'net_income' => 0 // 需要计算
                ];
            }

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'total_revenue' => $total_revenue,
                    'active_users' => $active_users,
                    'new_users' => $new_users,
                    'conversion_rate' => $conversion_rate,
                    'trend_data' => $trend_data,
                    'revenue_composition' => $revenue_composition,
                    'user_ranking' => $user_ranking,
                    'project_ranking' => $project_ranking,
                    'team_ranking' => $team_ranking,
                    'detail_data' => $detail_data
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取KYC统计
     */
    public function getKycStats()
    {
        try {
            $pending = Db::name('pd_kyc')->where('status', 0)->count();
            $approved = Db::name('pd_kyc')->where('status', 1)->count();
            $rejected = Db::name('pd_kyc')->where('status', 2)->count();
            $total = $pending + $approved + $rejected;
            $rate = $total > 0 ? round($approved / $total * 100, 2) . '%' : '0%';

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'pending' => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                    'rate' => $rate
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取KYC列表
     */
    public function getKycList()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 20);
            $status = Request::get('status', '');
            $search = Request::get('search', '');

            $where = [];

            if ($status !== '') {
                $where[] = ['k.status', '=', $status];
            }

            if ($search) {
                $where[] = ['u.username|k.real_name|k.id_card', 'like', "%{$search}%"];
            }

            $list = Db::name('pd_kyc')->alias('k')
                ->join('pd_user u', 'k.user_id = u.id')
                ->where($where)
                ->field('k.*, u.username, u.phone')
                ->order('k.id', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_kyc')->alias('k')
                ->join('pd_user u', 'k.user_id = u.id')
                ->where($where)
                ->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 审核KYC
     */
    public function approveKyc()
    {
        try {
            $id = Request::post('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            Db::name('pd_kyc')->where('id', $id)->update([
                'status' => 1,
                'verified_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 更新用户KYC状态
            $kyc = Db::name('pd_kyc')->where('id', $id)->find();
            if ($kyc) {
                Db::name('pd_user')->where('id', $kyc['user_id'])->update(['kyc_status' => 1]);
            }

            // 记录操作日志
            $this->addLog('approve_kyc', "通过KYC认证ID: {$id}");

            return json(['code' => 1, 'msg' => '审核通过', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 拒绝KYC
     */
    public function rejectKyc()
    {
        try {
            $id = Request::post('id');
            $reason = Request::post('reason', '');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            Db::name('pd_kyc')->where('id', $id)->update([
                'status' => 2,
                'reject_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 记录操作日志
            $this->addLog('reject_kyc', "拒绝KYC认证ID: {$id}, 原因: {$reason}");

            return json(['code' => 1, 'msg' => '已拒绝', 'data' => null]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取KYC详情
     */
    public function getKycDetail()
    {
        try {
            $id = Request::get('id');

            if (!$id) {
                return json(['code' => -1, 'msg' => '参数错误', 'data' => null]);
            }

            $detail = Db::name('pd_kyc')->alias('k')
                ->join('pd_user u', 'k.user_id = u.id')
                ->where('k.id', $id)
                ->field('k.*, u.username, u.phone')
                ->find();

            return json(['code' => 1, 'msg' => '获取成功', 'data' => $detail]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取操作日志
     */
    public function getLogs()
    {
        try {
            $page = Request::get('page', 1);
            $limit = Request::get('limit', 50);

            $list = Db::name('pd_admin_logs')->alias('l')
                ->join('pd_admins a', 'l.admin_id = a.id')
                ->field('l.*, a.username')
                ->order('l.id', 'desc')
                ->page($page, $limit)
                ->select();

            $total = Db::name('pd_admin_logs')->count();

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '获取失败: ' . $e->getMessage(), 'data' => null]);
        }
    }
}
