<?php
/**
 * 积分服务
 */

namespace app\service;

use app\common\model\User;
use app\common\model\WalletLog;
use think\facade\Db;

class PointsService
{
    /**
     * 积分兑换商品
     * @param int $userId 用户ID
     * @param int $goodsId 商品ID
     * @param int $quantity 兑换数量
     * @return array
     */
    public static function exchangeGoods($userId, $goodsId, $quantity = 1)
    {
        // 获取商品信息（需要创建goods表）
        $goods = Db::name('points_goods')->where('id', $goodsId)->find();

        if (!$goods) {
            return ['code' => -1, 'msg' => '商品不存在', 'data' => null];
        }

        if ($goods['status'] != 1) {
            return ['code' => -1, 'msg' => '商品已下架', 'data' => null];
        }

        $totalPoints = bcmul($goods['points_price'], $quantity, 8);

        $user = User::find($userId);
        if (!$user) {
            return ['code' => -1, 'msg' => '用户不存在', 'data' => null];
        }

        $userPoints = $user->points ?? 0;
        if (bccomp($userPoints, $totalPoints, 8) < 0) {
            return ['code' => -1, 'msg' => '积分不足', 'data' => null];
        }

        Db::startTrans();
        try {
            // 扣除积分
            $user->points = bcsub($userPoints, $totalPoints, 8);
            $user->save();

            // 记录积分变动
            WalletLog::create([
                'user_id' => $userId,
                'type' => 'points_exchange',
                'amount' => -$totalPoints,
                'currency' => 'points',
                'balance_before' => $userPoints,
                'balance_after' => $user->points,
                'remark' => "兑换商品：{$goods['name']} x{$quantity}",
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // 创建兑换订单（需要创建points_exchange_order表）
            Db::name('points_exchange_order')->insert([
                'user_id' => $userId,
                'goods_id' => $goodsId,
                'quantity' => $quantity,
                'points_cost' => $totalPoints,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();

            return [
                'code' => 1,
                'msg' => '兑换成功',
                'data' => [
                    'order_id' => Db::name('points_exchange_order')->getLastInsID(),
                    'points_cost' => $totalPoints,
                    'remaining_points' => $user->points
                ]
            ];
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'msg' => '兑换失败：' . $e->getMessage(), 'data' => null];
        }
    }
}
