<?php
namespace app\api\controller;

use app\common\model\InvestProject;
use app\common\model\VipLevel;

class Project
{
    /**
     * 项目列表
     */
    public function index()
    {
        $currency = input('currency', '');
        $page = input('page', 1);
        $limit = input('limit', 10);

        $where = [['status', '=', 1]];

        if ($currency) {
            $where[] = ['currency', '=', $currency];
        }

        $list = InvestProject::where($where)
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = InvestProject::where($where)->count();

        // 获取用户VIP等级（如果已登录）
        $userId = request()->userId ?? 0;
        $vipBonus = 0;
        if ($userId) {
            $user = \app\common\model\User::find($userId);
            $vipLevel = VipLevel::where('level', $user->vip_level)->find();
            $vipBonus = $vipLevel ? $vipLevel->interest_bonus : 0;
        }

        foreach ($list as &$item) {
            // 计算剩余额度
            $item->remain = bcsub($item->total_amount ?? '0', $item->sold_amount ?? '0', 8);

            // 计算进度
            $progress = 0;
            if (bccomp($item->total_amount ?? '0', '0', 8) > 0) {
                $progress = bcmul(bcdiv($item->sold_amount ?? '0', $item->total_amount, 8), '100', 8);
            }
            $item->progress = round((float)$progress, 2);

            // 计算用户实际收益率
            $userRate = bcadd($item->rate ?? '0', (string)$vipBonus, 8);
            if ($item->currency === 'USDT') {
                $userRate = bcadd($userRate, $item->usdt_bonus ?? '0', 8);
            }
            $item->user_rate = $userRate;

            // 确保字段名与前端匹配
            $item->daily_rate = $item->rate ?? $item->daily_rate ?? '0';
            $item->period = $item->period ?? $item->cycle ?? 0;
            $item->min_amount = $item->min_amount ?? 0;
            $item->max_amount = $item->max_amount ?? 0;
            $item->total_amount = $item->total_amount ?? $item->total_limit ?? '0';
            $item->sold_amount = $item->sold_amount ?? '0';
        }

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }

    /**
     * 获取项目分类列表（用户端）
     * GET /api/projects/categories
     */
    public function categories()
    {
        // 从项目分类表获取分类列表
        $categories = \think\facade\Db::name('project_categories')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select();

        return success('获取成功', [
            'list' => $categories
        ]);
    }

    /**
     * 项目详情
     */
    public function detail()
    {
        $projectId = input('id');

        $project = InvestProject::find($projectId);

        if (!$project) {
            return error('项目不存在');
        }

        // 计算剩余额度
        $project->remain = bcsub($project->total_amount ?? '0', $project->sold_amount ?? '0', 8);

        // 计算进度
        $progress = 0;
        if (bccomp($project->total_amount ?? '0', '0', 8) > 0) {
            $progress = bcmul(bcdiv($project->sold_amount ?? '0', $project->total_amount, 8), '100', 8);
        }
        $project->progress = round((float)$progress, 2);

        // 确保字段名与前端匹配
        $project->daily_rate = $project->rate ?? $project->daily_rate ?? '0';
        $project->period = $project->period ?? $project->cycle ?? 0;

        // 计算倒计时（认购结束时间）
        $saleEndTime = $project->sale_end_time ?? $project->end_time ?? null;
        $countdown = null;
        if ($saleEndTime) {
            $endTimestamp = is_numeric($saleEndTime) ? $saleEndTime : strtotime($saleEndTime);
            $now = time();
            $remaining = max(0, $endTimestamp - $now);

            $days = floor($remaining / 86400);
            $hours = floor(($remaining % 86400) / 3600);
            $minutes = floor(($remaining % 3600) / 60);

            $countdown = [
                'days' => str_pad($days, 2, '0', STR_PAD_LEFT),
                'hours' => str_pad($hours, 2, '0', STR_PAD_LEFT),
                'minutes' => str_pad($minutes, 2, '0', STR_PAD_LEFT),
                'format' => sprintf('%02d-%02d-%02d', $days, $hours, $minutes),
                'timestamp' => $endTimestamp,
                'remaining_seconds' => $remaining
            ];
        }
        $project->countdown = $countdown;

        // 购买和返利信息
        $project->purchase_info = [
            'cny' => [
                'rate' => $project->rate ?? '0',
                'rebate' => $project->cny_rebate ?? $project->rebate_cny ?? '0'
            ],
            'usdt' => [
                'rate' => bcadd($project->rate ?? '0', $project->usdt_bonus ?? '0', 8),
                'rebate' => $project->usdt_rebate ?? $project->rebate_usdt ?? '0'
            ]
        ];

        return success('获取成功', $project);
    }
}
