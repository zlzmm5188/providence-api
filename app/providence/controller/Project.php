<?php
/**
 * 项目管理控制器
 */

namespace app\providence\controller;

use app\common\model\InvestProject as InvestProjectModel;
use think\facade\Db;

class Project
{
    /**
     * 获取项目列表
     * GET /providence/projects
     *
     * 参数：
     * - keyword: 搜索关键词（项目名称）
     * - page: 页码（默认1）
     * - pageSize: 每页数量（默认20）
     * - status: 状态筛选（0-下线，1-上线，2-已完成）
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "获取成功",
     *   "data": {
     *     "list": [
     *       {
     *         "project_id": 1,
     *         "name": "项目名称",
     *         "rate": 0.5,
     *         "min_amount": 1000,
     *         "max_amount": 100000,
     *         "total_amount": 1000000,
     *         "sold_amount": 500000,
     *         "invested": 500000,
     *         "investors": 50,
     *         "progress": 50.00,
     *         "period": 30,
     *         "status": 1
     *       }
     *     ],
     *     "total": 100
     *   }
     * }
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');

        $where = [];

        // 搜索关键词
        if (!empty($keyword)) {
            $where[] = ['name', 'like', "%{$keyword}%"];
        }

        // 状态筛选
        if ($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }

        $list = InvestProjectModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 计算已投金额和进度
        foreach ($list as &$item) {
            $item->invested = $item->sold_amount ?? 0;
            $item->investors = Db::name('invest_orders')
                ->where('project_id', $item->id)
                ->group('user_id')
                ->count();

            // 计算进度
            if ($item->total_amount > 0) {
                $item->progress = round(($item->sold_amount / $item->total_amount) * 100, 2);
            } else {
                $item->progress = 0;
            }
        }

        $total = InvestProjectModel::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取项目详情
     * GET /providence/projects/:id
     *
     * 参数：
     * - id: 项目ID（路径参数）
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "获取成功",
     *   "data": {项目详情对象}
     * }
     */
    public function detail()
    {
        $id = input('param.id', 0, 'intval');

        $project = InvestProjectModel::find($id);
        if (!$project) {
            return error('项目不存在');
        }

        $project->invested = $project->sold_amount ?? 0;
        $project->investors = Db::name('invest_orders')
            ->where('project_id', $id)
            ->group('user_id')
            ->count();

        return success('获取成功', $project);
    }

    /**
     * 创建项目
     * POST /providence/projects
     *
     * 参数（POST body）：
     * - name: 项目名称（必填）
     * - dailyRate: 日收益率（必填）
     * - minAmount: 起投金额（必填）
     * - maxAmount: 单笔限额（必填）
     * - totalLimit: 项目总额（必填）
     * - period: 周期天数（必填）
     * - subtitle: 副标题
     * - description: 项目描述
     * - currency: 币种（CNY/USDT，默认CNY）
     * - status: 状态（0-下线，1-上线，默认0）
     * - category: 项目板块
     * - usdtBonusRate: USDT加息比例
     * - vipLevel: VIP限购等级
     * - isHot: 是否热门（0/1）
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "创建成功",
     *   "data": {项目对象}
     * }
     */
    public function create()
    {
        $data = input('post.');

        // 验证必填字段
        $required = ['name', 'dailyRate', 'minAmount', 'maxAmount', 'totalLimit', 'period'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return error("缺少必填字段: {$field}");
            }
        }

        try {
            $project = InvestProjectModel::create([
                'name' => $data['name'],
                'subtitle' => $data['subtitle'] ?? '',
                'description' => $data['description'] ?? '',
                'rate' => $data['dailyRate'],
                'min_amount' => $data['minAmount'],
                'max_amount' => $data['maxAmount'],
                'total_amount' => $data['totalLimit'],
                'period' => $data['period'],
                'currency' => $data['currency'] ?? 'CNY',
                'status' => $data['status'] ?? 0,
                'category' => $data['category'] ?? '',
                'usdt_bonus' => $data['usdtBonusRate'] ?? 0,
                'vip_level' => $data['vipLevel'] ?? 0,
                'is_hot' => $data['isHot'] ?? 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return success('创建成功', $project);
        } catch (\Exception $e) {
            return error('创建失败：' . $e->getMessage());
        }
    }

    /**
     * 更新项目
     * PUT /providence/projects/:id
     *
     * 参数：
     * - id: 项目ID（路径参数）
     * - 其他字段同创建接口
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "更新成功",
     *   "data": {项目对象}
     * }
     */
    public function update()
    {
        $id = input('param.id', 0, 'intval');
        $data = input('post.');

        $project = InvestProjectModel::find($id);
        if (!$project) {
            return error('项目不存在');
        }

        try {
            $updateData = [];
            $fieldMap = [
                'name' => 'name',
                'subtitle' => 'subtitle',
                'description' => 'description',
                'dailyRate' => 'rate',
                'minAmount' => 'min_amount',
                'maxAmount' => 'max_amount',
                'totalLimit' => 'total_amount',
                'period' => 'period',
                'status' => 'status',
                'category' => 'category',
                'usdtBonusRate' => 'usdt_bonus',
                'vipLevel' => 'vip_level',
                'isHot' => 'is_hot'
            ];

            foreach ($fieldMap as $frontendField => $backendField) {
                if (isset($data[$frontendField])) {
                    $updateData[$backendField] = $data[$frontendField];
                }
            }

            $project->save($updateData);

            return success('更新成功', $project);
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage());
        }
    }

    /**
     * 删除项目
     * DELETE /providence/projects/:id
     *
     * 参数：
     * - id: 项目ID（路径参数）
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "删除成功",
     *   "data": null
     * }
     */
    public function delete()
    {
        $id = input('param.id', 0, 'intval');

        $project = InvestProjectModel::find($id);
        if (!$project) {
            return error('项目不存在');
        }

        // 检查是否有投资订单
        $orderCount = Db::name('invest_orders')->where('project_id', $id)->count();
        if ($orderCount > 0) {
            return error('该项目已有投资订单，无法删除');
        }

        try {
            $project->delete();
            return success('删除成功');
        } catch (\Exception $e) {
            return error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量更新状态
     * POST /providence/projects/batch-status
     *
     * 参数：
     * - ids: 项目ID数组（必填）
     * - status: 状态值（0-下线，1-上线，2-已完成）
     *
     * 返回：
     * {
     *   "code": 1,
     *   "msg": "批量更新成功",
     *   "data": null
     * }
     */
    public function batchStatus()
    {
        $ids = input('post.ids', []);
        $status = input('post.status', 0, 'intval');

        if (empty($ids) || !is_array($ids)) {
            return error('请选择要操作的项目');
        }

        try {
            InvestProjectModel::whereIn('id', $ids)->update(['status' => $status]);

            return success('批量更新成功');
        } catch (\Exception $e) {
            return error('批量更新失败：' . $e->getMessage());
        }
    }
}
