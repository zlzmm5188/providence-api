<?php
/**
 * 钱包流水管理控制器
 */

namespace app\providence\controller;

use app\common\model\WalletLog as WalletLogModel;
use app\common\model\User;
use think\facade\Db;

class WalletLog
{
    /**
     * 获取钱包流水列表
     * GET /providence/wallet-logs
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

        $list = WalletLogModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            $user = User::find($item->user_id);  // user_id 存储的是 users.id，正确
            $item->username = $user ? $user->username : '';
            $item->uid = $user ? (string)($user->uid ?? $user->id) : (string)$item->user_id;
        }

        $total = WalletLogModel::where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }
}
