<?php
/**
 * 收益记录管理控制器
 */

namespace app\providence\controller;

use app\common\model\WalletLog;
use app\common\model\User;
use think\facade\Db;

class Earning
{
    /**
     * 获取收益记录列表
     * GET /providence/earnings
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $currency = input('get.currency', '', 'trim');
        $type = input('get.type', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        // 只查询收益类型的记录
        $where[] = ['type', 'in', ['invest_profit', 'ribao_profit', 'referral_reward']];

        // 搜索关键词（用户名）
        if (!empty($keyword)) {
            $userIds = User::where('username', 'like', "%{$keyword}%")->column('id');
            if (!empty($userIds)) {
                $where[] = ['user_id', 'in', $userIds];
            } else {
                return success('获取成功', ['list' => [], 'total' => 0]);
            }
        }

        // 币种筛选
        if (!empty($currency)) {
            $where[] = ['currency', '=', strtoupper($currency)];
        }

        // 类型筛选
        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        // 时间筛选
        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $list = WalletLog::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            $user = User::find($item->user_id);  // user_id 存储的是 users.id，正确
            $item->username = $user ? $user->username : '';
            $item->uid = $user ? (string)($user->uid ?? $user->id) : (string)$item->user_id;

            // 从remark中提取订单号和项目名
            if (preg_match('/订单号[：:]([^\s]+)/', $item->remark, $orderMatch)) {
                $item->order_no = $orderMatch[1];
            }
            if (preg_match('/项目[：:]([^\s]+)/', $item->remark, $projectMatch)) {
                $item->project_name = $projectMatch[1];
            }

            // 从remark中提取订单ID
            if (preg_match('/订单ID[：:](\d+)/', $item->remark, $orderIdMatch)) {
                $item->order_id = intval($orderIdMatch[1]);
            }
        }

        $total = WalletLog::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }
}
