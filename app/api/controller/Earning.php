<?php
namespace app\api\controller;

use app\common\model\Earning as EarningModel;
use think\facade\Db;

class Earning
{
    /**
     * 获取收益记录列表
     * GET /api/orders/earnings
     */
    public function list()
    {
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();
        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 10);

        $list = EarningModel::where('user_id', $userId)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = EarningModel::where('user_id', $userId)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }
}
