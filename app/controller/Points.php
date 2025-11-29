<?php
/**
 * 积分兑换控制器
 */

namespace app\api\controller;

use app\common\middleware\AuthMiddleware;
use app\common\model\User;
use app\common\model\WalletLog;
use app\service\PointsService;
use think\facade\Db;

class Points
{
    /**
     * 积分兑换商品
     * POST /api/points/exchange
     */
    public function exchange()
    {
        $userId = request()->userId;
        $goodsId = input('post.goods_id', 0, 'intval');
        $quantity = input('post.quantity', 1, 'intval');

        if ($goodsId <= 0) {
            return json(['code' => -1, 'msg' => '商品ID不能为空', 'data' => null]);
        }

        if ($quantity <= 0) {
            return json(['code' => -1, 'msg' => '兑换数量必须大于0', 'data' => null]);
        }

        try {
            $result = PointsService::exchangeGoods($userId, $goodsId, $quantity);
            return json($result);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '兑换失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取积分余额
     * GET /api/points/balance
     */
    public function balance()
    {
        $userId = request()->userId;
        $user = User::find($userId);

        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'points' => $user->points ?? 0,
                'user_id' => $userId
            ]
        ]);
    }

    /**
     * 获取积分记录
     * GET /api/points/logs
     */
    public function logs()
    {
        $userId = request()->userId;
        $page = input('get.page', 1, 'intval');
        $limit = input('get.limit', 20, 'intval');

        $logs = WalletLog::where('user_id', $userId)
            ->where('currency', 'points')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $total = WalletLog::where('user_id', $userId)
            ->where('currency', 'points')
            ->count();

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $logs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]
        ]);
    }
}
