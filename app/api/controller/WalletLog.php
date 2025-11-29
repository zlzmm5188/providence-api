<?php
namespace app\api\controller;

use app\common\model\WalletLog as WalletLogModel;
use think\facade\Db;

class WalletLog
{
    /**
     * 获取钱包流水列表（用户端）
     * GET /api/finance/wallet-logs
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

        $currency = input('currency', '');
        $type = input('type', '');
        $page = input('page', 1);
        $limit = input('limit', 10);

        $where = [['user_id', '=', $userId]];

        if ($currency) {
            $where[] = ['currency', '=', $currency];
        }

        if ($type) {
            $where[] = ['type', '=', $type];
        }

        $list = WalletLogModel::where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = WalletLogModel::where($where)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }
}
