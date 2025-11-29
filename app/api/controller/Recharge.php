<?php
namespace app\api\controller;

use app\common\model\RechargeOrder;

class Recharge
{
    public function add()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $currency = input('currency');
        $amount = input('amount');
        $paymentMethod = input('payment_method', '');
        $paymentProof = input('payment_proof', '');

        if (bcmath_comp($amount, '0') <= 0) {
            return error('金额必须大于0');
        }

        $orderNo = generate_order_no('R');

        $order = RechargeOrder::create([
            'order_no' => $orderNo,
            'user_id' => $userId,
            'currency' => $currency,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_proof' => $paymentProof,
            'status' => 0
        ]);

        return success('充值申请已提交', [
            'order_id' => $order->id,
            'order_no' => $orderNo,
            'amount' => $amount,
            'status' => 0
        ]);
    }

    public function list()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        $userId = get_user_id();

        if (!$userId) {
            return error('请先登录', [], 401);
        }

        $page = input('page', 1);
        $limit = input('limit', 10);
        $status = input('status', ''); // 可选：筛选状态

        $query = RechargeOrder::where('user_id', $userId);

        // 如果指定了状态，则筛选
        if ($status !== '') {
            $query->where('status', $status);

            // 如果查询待支付订单，只返回未过期的（30分钟内）
            if ($status == 0) {
                $expireTime = date('Y-m-d H:i:s', time() - 1800);
                $query->where('created_at', '>=', $expireTime);
            }
        }

        $list = $query->page($page, $limit)
            ->order('id', 'desc')
            ->select();

        $total = $query->count();

        // 格式化返回数据
        $formattedList = [];
        foreach ($list as $item) {
            $formattedList[] = [
                'id' => $item['id'],
                'order_no' => $item['order_no'],
                'amount' => (float)$item['amount'],
                'currency' => $item['currency'],
                'payment_method' => $item['payment_method'],
                'status' => (int)$item['status'],
                'created_at' => $item['created_at'],
                'paid_at' => $item['paid_at'] ?? null,
                'remaining_time' => $item['status'] == 0 ? (15 * 60 - (time() - strtotime($item['created_at']))) : 0 // 15分钟倒计时
            ];
        }

        return success('获取成功', [
            'total' => $total,
            'list' => $formattedList
        ]);
    }
}
