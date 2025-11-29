<?php
/**
 * 订单管理控制器
 */

namespace app\providence\controller;

use app\common\model\InvestOrder as InvestOrderModel;
use app\common\model\User;
use think\facade\Db;

class Order
{
    /**
     * 获取投资订单列表
     * GET /providence/orders
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $currency = input('get.currency', '', 'trim');
        $status = input('get.status', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        // 搜索关键词（用户名或订单号）
        if (!empty($keyword)) {
            $userIds = User::where('username', 'like', "%{$keyword}%")->column('id');
            $where[] = function($query) use ($keyword, $userIds) {
                $query->where('order_no', 'like', "%{$keyword}%")
                      ->whereOr('user_id', 'in', $userIds);
            };
        }

        // 币种筛选
        if (!empty($currency)) {
            $where[] = ['currency', '=', strtoupper($currency)];
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

        $list = InvestOrderModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户和项目信息
        foreach ($list as &$item) {
            $user = User::find($item->user_id);  // user_id 存储的是 users.id，正确
            $item->username = $user ? $user->username : '';
            $item->uid = $user ? (string)($user->uid ?? $user->id) : (string)$item->user_id;

            // 计算剩余天数
            if ($item->end_date) {
                $endDate = strtotime($item->end_date);
                $now = time();
                $item->remaining_days = max(0, ceil(($endDate - $now) / 86400));
            } else {
                $item->remaining_days = 0;
            }
        }

        $total = InvestOrderModel::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取订单详情
     * GET /providence/orders/:id
     */
    public function detail()
    {
        $id = input('param.id', 0, 'intval');

        $order = InvestOrderModel::find($id);
        if (!$order) {
            return error('订单不存在');
        }

        $user = User::find($order->user_id);  // user_id 存储的是 users.id，正确
        $order->username = $user ? $user->username : '';
        $order->uid = $user ? (string)($user->uid ?? $user->id) : (string)$order->user_id;

        // 计算剩余天数
        if ($order->end_date) {
            $endDate = strtotime($order->end_date);
            $now = time();
            $order->remaining_days = max(0, ceil(($endDate - $now) / 86400));
        } else {
            $order->remaining_days = 0;
        }

        return success('获取成功', $order);
    }
}
