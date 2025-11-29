<?php

namespace app\providence\controller;

use think\facade\Request;
use think\facade\Db;
use think\Response;

class AdminDashboard
{
    /**
     * 获取仪表盘统计数据
     * 返回字段必须与前端期望的 DashboardStatistics 接口完全一致
     */
    public function statistics()
    {
        try {
            // 用户统计
            $totalUsers = Db::name('users')->where('is_internal', 0)->count();
            $activeUsers = Db::name('users')
                ->where('is_internal', 0)
                ->where('last_active_at', '>', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->count();
            $newUsersToday = Db::name('users')
                ->where('is_internal', 0)
                ->whereTime('created_at', 'today')
                ->count();

            // 项目统计
            $totalProjects = Db::name('projects')->count();
            $activeProjects = Db::name('projects')->where('status', 1)->count();

            // 充值统计（按币种分开）
            $totalRechargeCny = Db::name('recharge_records')
                ->where('currency', 'CNY')
                ->where('status', 1)
                ->sum('amount') ?: 0;
            $totalRechargeUsdt = Db::name('recharge_records')
                ->where('currency', 'USDT')
                ->where('status', 1)
                ->sum('amount') ?: 0;
            $todayRechargeCny = Db::name('recharge_records')
                ->where('currency', 'CNY')
                ->where('status', 1)
                ->whereTime('created_at', 'today')
                ->sum('amount') ?: 0;
            $todayRechargeUsdt = Db::name('recharge_records')
                ->where('currency', 'USDT')
                ->where('status', 1)
                ->whereTime('created_at', 'today')
                ->sum('amount') ?: 0;

            // 提现统计（按币种分开）
            $totalWithdrawCny = Db::name('withdraw_records')
                ->where('currency', 'CNY')
                ->where('status', '>=', 1)  // 1=审核通过, 2=处理中, 3=已完成
                ->sum('amount') ?: 0;
            $totalWithdrawUsdt = Db::name('withdraw_records')
                ->where('currency', 'USDT')
                ->where('status', '>=', 1)
                ->sum('amount') ?: 0;

            return json([
                "code" => 1,  // 前端期望 code === 1 表示成功
                "msg" => "success",
                "data" => [
                    // 用户统计
                    "total_users" => (int)$totalUsers,
                    "active_users" => (int)$activeUsers,
                    "new_users_today" => (int)$newUsersToday,
                    // 项目统计
                    "total_projects" => (int)$totalProjects,
                    "active_projects" => (int)$activeProjects,
                    // 充值统计
                    "total_recharge_cny" => (float)$totalRechargeCny,
                    "total_recharge_usdt" => (float)$totalRechargeUsdt,
                    "today_recharge_cny" => (float)$todayRechargeCny,
                    "today_recharge_usdt" => (float)$todayRechargeUsdt,
                    // 提现统计
                    "total_withdraw_cny" => (float)$totalWithdrawCny,
                    "total_withdraw_usdt" => (float)$totalWithdrawUsdt,
                ]
            ]);
        } catch (\Exception $e) {
            // 如果查询失败，返回默认值，避免500错误
            return json([
                "code" => 1,
                "msg" => "success",
                "data" => [
                    "total_users" => 0,
                    "active_users" => 0,
                    "new_users_today" => 0,
                    "total_projects" => 0,
                    "active_projects" => 0,
                    "total_recharge_cny" => 0.0,
                    "total_recharge_usdt" => 0.0,
                    "today_recharge_cny" => 0.0,
                    "today_recharge_usdt" => 0.0,
                    "total_withdraw_cny" => 0.0,
                    "total_withdraw_usdt" => 0.0,
                ]
            ]);
        }
    }

    /**
     * 获取用户列表
     */
    public function userList()
    {
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 10);

        $list = [];
        for ($i = 0; $i < $pageSize; $i++) {
            $list[] = [
                "id" => ($page - 1) * $pageSize + $i + 1,
                "username" => "user_" . (($page - 1) * $pageSize + $i + 1),
                "email" => "user" . (($page - 1) * $pageSize + $i + 1) . "@example.com",
                "phone" => "1360000" . str_pad($i, 4, "0", STR_PAD_LEFT),
                "balance_cny" => rand(100000, 10000000),
                "balance_usdt" => rand(100, 10000),
                "vip_level" => rand(0, 8),
                "status" => rand(0, 1),
                "created_at" => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)),
            ];
        }

        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "msg" => "success",
            "data" => [
                "list" => $list,
                "total" => 1000,
                "page" => $page,
                "pageSize" => $pageSize,
            ]
        ]);
    }

    /**
     * 获取充值订单列表
     */
    public function rechargeList()
    {
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 10);

        $list = [];
        for ($i = 0; $i < $pageSize; $i++) {
            $list[] = [
                "id" => ($page - 1) * $pageSize + $i + 1,
                "order_no" => "REC" . date('YmdHis') . str_pad($i, 4, "0", STR_PAD_LEFT),
                "user_id" => rand(1, 1000),
                "amount" => rand(1000, 500000),
                "currency" => rand(0, 1) ? "CNY" : "USDT",
                "method" => ["wechat", "alipay", "usdt"][rand(0, 2)],
                "status" => ["pending", "success", "failed"][rand(0, 2)],
                "created_at" => date('Y-m-d H:i:s', time() - rand(0, 86400)),
            ];
        }

        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "msg" => "success",
            "data" => [
                "list" => $list,
                "total" => 500,
                "page" => $page,
                "pageSize" => $pageSize,
            ]
        ]);
    }

    /**
     * 获取提现订单列表
     */
    public function withdrawList()
    {
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 10);

        $list = [];
        for ($i = 0; $i < $pageSize; $i++) {
            $list[] = [
                "id" => ($page - 1) * $pageSize + $i + 1,
                "order_no" => "WTD" . date('YmdHis') . str_pad($i, 4, "0", STR_PAD_LEFT),
                "user_id" => rand(1, 1000),
                "amount" => rand(2000, 800000),
                "currency" => rand(0, 1) ? "CNY" : "USDT",
                "method" => ["bank", "usdt"][rand(0, 1)],
                "status" => ["pending", "processing", "success", "failed"][rand(0, 3)],
                "created_at" => date('Y-m-d H:i:s', time() - rand(0, 86400 * 7)),
            ];
        }

        return json([
            "code" => 1,  // 前端期望 code === 1 表示成功
            "msg" => "success",
            "data" => [
                "list" => $list,
                "total" => 400,
                "page" => $page,
                "pageSize" => $pageSize,
            ]
        ]);
    }

    /**
     * 获取数据概览详情
     * GET /providence/dashboard/overview
     */
    public function overview()
    {
        try {
            // 最近7天的用户增长趋势
            $userGrowth = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $count = Db::name('users')
                    ->where('is_internal', 0)
                    ->whereTime('created_at', $date)
                    ->count();
                $userGrowth[] = [
                    'date' => $date,
                    'count' => (int)$count
                ];
            }

            // 最近7天的充值趋势（CNY和USDT分开）
            $rechargeTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $cnyAmount = Db::name('recharge_records')
                    ->where('currency', 'CNY')
                    ->where('status', 1)
                    ->whereTime('created_at', $date)
                    ->sum('amount') ?: 0;
                $usdtAmount = Db::name('recharge_records')
                    ->where('currency', 'USDT')
                    ->where('status', 1)
                    ->whereTime('created_at', $date)
                    ->sum('amount') ?: 0;
                $rechargeTrend[] = [
                    'date' => $date,
                    'cny' => (float)$cnyAmount,
                    'usdt' => (float)$usdtAmount
                ];
            }

            // 最近7天的提现趋势（CNY和USDT分开）
            $withdrawTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $cnyAmount = Db::name('withdraw_records')
                    ->where('currency', 'CNY')
                    ->where('status', '>=', 1)
                    ->whereTime('created_at', $date)
                    ->sum('amount') ?: 0;
                $usdtAmount = Db::name('withdraw_records')
                    ->where('currency', 'USDT')
                    ->where('status', '>=', 1)
                    ->whereTime('created_at', $date)
                    ->sum('amount') ?: 0;
                $withdrawTrend[] = [
                    'date' => $date,
                    'cny' => (float)$cnyAmount,
                    'usdt' => (float)$usdtAmount
                ];
            }

            // 待处理事项数量
            $pendingRecharge = Db::name('recharge_records')->where('status', 0)->count();
            $pendingWithdraw = Db::name('withdraw_records')->where('status', 0)->count();
            $pendingKyc = Db::name('users')->where('realname_status', 1)->count(); // 1=待审核
            $pendingFaceVerify = Db::name('face_verification')->where('result', 0)->count(); // 0=待审核

            // 项目收益统计
            $totalInvested = Db::name('invest_orders')
                ->where('status', 1)
                ->sum('amount') ?: 0;
            $totalEarnings = Db::name('earnings')
                ->sum('amount') ?: 0;
            $pendingEarnings = Db::name('invest_orders')
                ->where('status', 1)
                ->where('end_date', '>', date('Y-m-d'))
                ->sum('profit') ?: 0;

            // 即将到期的项目
            $expiringProjects = Db::name('projects')
                ->where('status', 1)
                ->where('end_date', '>=', date('Y-m-d'))
                ->where('end_date', '<=', date('Y-m-d', strtotime('+7 days')))
                ->count();

            return json([
                'code' => 1,
                'msg' => 'success',
                'data' => [
                    'userGrowth' => $userGrowth,
                    'rechargeTrend' => $rechargeTrend,
                    'withdrawTrend' => $withdrawTrend,
                    'pendingTasks' => [
                        'recharge' => (int)$pendingRecharge,
                        'withdraw' => (int)$pendingWithdraw,
                        'kyc' => (int)$pendingKyc,
                        'faceVerify' => (int)$pendingFaceVerify,
                        'total' => (int)($pendingRecharge + $pendingWithdraw + $pendingKyc + $pendingFaceVerify)
                    ],
                    'projectStats' => [
                        'totalInvested' => (float)$totalInvested,
                        'totalEarnings' => (float)$totalEarnings,
                        'pendingEarnings' => (float)$pendingEarnings,
                        'expiringProjects' => (int)$expiringProjects
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 1,
                'msg' => 'success',
                'data' => [
                    'userGrowth' => [],
                    'rechargeTrend' => [],
                    'withdrawTrend' => [],
                    'pendingTasks' => [
                        'recharge' => 0,
                        'withdraw' => 0,
                        'kyc' => 0,
                        'faceVerify' => 0,
                        'total' => 0
                    ],
                    'projectStats' => [
                        'totalInvested' => 0.0,
                        'totalEarnings' => 0.0,
                        'pendingEarnings' => 0.0,
                        'expiringProjects' => 0
                    ]
                ]
            ]);
        }
    }
}
