<?php
namespace app\api\controller;

use think\facade\Db;
use think\facade\Request;

class BotAdmin
{
    /**
     * 发布项目（Bot专用）
     */
    public function publishProject()
    {
        // 验证管理员权限
        $adminToken = Request::header('Admin-Token');
        if ($adminToken !== 'providence_admin_2025') {
            return json(['code' => 0, 'msg' => '无权限']);
        }
        
        // 获取参数
        $data = Request::post();
        
        // 验证必填字段
        if (empty($data['name']) || empty($data['rate']) || empty($data['cycle'])) {
            return json(['code' => 0, 'msg' => '缺少必填字段']);
        }
        
        // 插入数据库
        try {
            $id = Db::name('projects')->insertGetId([
                'name' => $data['name'],
                'rate' => $data['rate'],
                'cycle' => $data['cycle'],
                'min_amount' => $data['min_amount'] ?? 1000,
                'max_amount' => $data['max_amount'] ?? 100000,
                'description' => $data['description'] ?? '',
                'status' => 1,
                'sort' => $data['sort'] ?? 100,
                'create_time' => time(),
                'update_time' => time()
            ]);
            
            return json(['code' => 1, 'msg' => '项目发布成功', 'data' => ['id' => $id]]);
            
        } catch (\Exception $e) {
            return json(['code' => 0, 'msg' => '发布失败: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 获取财务统计（用于AI分析）
     */
    public function getFinanceStats()
    {
        $adminToken = Request::header('Admin-Token');
        if ($adminToken !== 'providence_admin_2025') {
            return json(['code' => 0, 'msg' => '无权限']);
        }
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // 昨日充值
        $yesterdayRecharge = Db::name('recharge_records')
            ->whereTime('create_time', 'between', [strtotime($yesterday), strtotime($yesterday) + 86399])
            ->where('status', 1)
            ->sum('amount');
        
        // 今日充值
        $todayRecharge = Db::name('recharge_records')
            ->whereTime('create_time', 'today')
            ->where('status', 1)
            ->sum('amount');
        
        // 今日提现
        $todayWithdraw = Db::name('withdraw_records')
            ->whereTime('create_time', 'today')
            ->where('status', 1)
            ->sum('amount');
        
        // 明日到期项目（需要退款）
        $tomorrowExpire = Db::name('investments')
            ->whereTime('expire_time', 'between', [strtotime($tomorrow), strtotime($tomorrow) + 86399])
            ->where('status', 1)
            ->sum('amount');
        
        // 当前可用资金
        $availableFunds = $todayRecharge - $todayWithdraw;
        
        // 热门项目
        $hotProjects = Db::name('projects')
            ->where('status', 1)
            ->order('sort desc, id desc')
            ->limit(5)
            ->select()
            ->toArray();
        
        return json([
            'code' => 1,
            'data' => [
                'yesterday_recharge' => floatval($yesterdayRecharge),
                'today_recharge' => floatval($todayRecharge),
                'today_withdraw' => floatval($todayWithdraw),
                'tomorrow_expire' => floatval($tomorrowExpire),
                'available_funds' => floatval($availableFunds),
                'net_cash_flow' => floatval($todayRecharge - $todayWithdraw - $tomorrowExpire),
                'hot_projects' => $hotProjects
            ]
        ]);
    }
}
