<?php
namespace app\providence\controller;

use app\common\model\User;
use app\common\model\InvestProject;
use app\common\model\RechargeOrder;
use app\common\model\WithdrawOrder;

class Dashboard
{
    public function statistics()
    {
        $data = [
            'total_users' => User::count(),
            'active_users' => User::where('last_login_time', '>', date('Y-m-d H:i:s', strtotime('-30 days')))->count(),
            'new_users_today' => User::whereTime('created_at', 'today')->count(),
            'total_projects' => InvestProject::count(),
            'active_projects' => InvestProject::where('status', 1)->count(),
            'total_recharge_cny' => RechargeOrder::where('currency', 'CNY')->where('status', 1)->sum('amount'),
            'total_recharge_usdt' => RechargeOrder::where('currency', 'USDT')->where('status', 1)->sum('amount'),
            'today_recharge_cny' => RechargeOrder::where('currency', 'CNY')->where('status', 1)->whereTime('created_at', 'today')->sum('amount'),
            'today_recharge_usdt' => RechargeOrder::where('currency', 'USDT')->where('status', 1)->whereTime('created_at', 'today')->sum('amount'),
            'total_withdraw_cny' => WithdrawOrder::where('currency', 'CNY')->where('status', '>=', 1)->sum('amount'),
            'total_withdraw_usdt' => WithdrawOrder::where('currency', 'USDT')->where('status', '>=', 1)->sum('amount')
        ];
        
        return success('获取成功', $data);
    }
}
