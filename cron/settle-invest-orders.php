#!/usr/bin/env php
<?php
/**
 * 自动结算到期投资订单
 * 定时任务：每10分钟执行一次
 *
 * 功能：
 * 1. 查找所有到期的投资订单（status=1, end_date <= now）
 * 2. 自动结算：发放本金+收益到用户余额
 * 3. 发放推荐返利
 */

// 引入ThinkPHP框架
define('APP_PATH', __DIR__ . '/../app/');
require __DIR__ . '/../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\common\model\InvestOrder;
use app\common\model\User;
use app\common\model\WalletLog;
use app\common\service\ReferralService;
use think\facade\Db;
use think\facade\Log;

$logFile = __DIR__ . '/../runtime/log/settle-orders-' . date('Y-m-d') . '.log';

function writeLog($message) {
    global $logFile;
    $log = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($logFile, $log, FILE_APPEND);
    echo $log;
}

writeLog("========== 开始执行自动结算任务 ==========");

try {
    // 1. 查找所有到期的订单（status=1 运行中，end_date <= 当前时间）
    $now = date('Y-m-d H:i:s');
    $expiredOrders = InvestOrder::where('status', 1)
        ->where('end_date', '<=', $now)
        ->order('end_date', 'asc')
        ->limit(50) // 每次最多处理50个订单
        ->select();

    if (empty($expiredOrders)) {
        writeLog("没有到期的订单需要结算");
        exit(0);
    }

    writeLog("找到 " . count($expiredOrders) . " 个到期订单");

    $successCount = 0;
    $failCount = 0;

    foreach ($expiredOrders as $order) {
        try {
            Db::startTrans();

            // 重新锁定订单（防止并发）
            $order = InvestOrder::where('order_id', $order->order_id)
                ->where('status', 1)
                ->lock(true)
                ->find();

            if (!$order) {
                writeLog("订单 #{$order->order_id} 不存在或已结算，跳过");
                Db::rollback();
                continue;
            }

            // 锁定用户
            $user = User::where('id', $order->user_id)->lock(true)->find();
            if (!$user) {
                throw new \Exception("用户不存在: {$order->user_id}");
            }

            // 计算应发放金额（本金 + 收益）
            $amount = $order->amount;
            $totalProfit = $order->total_profit ?? $order->profit ?? '0';
            $totalAmount = bcmath_add($amount, $totalProfit);

            // 发放到用户余额
            $currency = $order->currency;
            $balanceField = $currency === 'CNY' ? 'balance_cny' : 'balance_usdt';
            $oldBalance = $user->$balanceField ?? '0';
            $newBalance = bcmath_add($oldBalance, $totalAmount);
            $user->$balanceField = $newBalance;
            $user->save();

            // 更新订单状态
            $order->status = 2; // 2 = 已完成
            $order->settled_at = date('Y-m-d H:i:s');
            $order->completed_at = date('Y-m-d H:i:s');
            $order->save();

            // 记录钱包流水
            WalletLog::create([
                'user_id' => $order->user_id,
                'currency' => $currency,
                'type' => 'invest_settle',
                'amount' => $totalAmount,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'related_id' => $order->order_id,
                'remark' => "投资结算：本金{$amount} + 收益{$totalProfit}"
            ]);

            Db::commit();

            // 发放推荐返利（事务外）
            try {
                ReferralService::processReward($order->order_id, $order->user_id, $amount, $currency);
                writeLog("订单 #{$order->order_id} 结算成功，返利已发放");
            } catch (\Exception $e) {
                writeLog("订单 #{$order->order_id} 结算成功，但返利发放失败: " . $e->getMessage());
            }

            $successCount++;

        } catch (\Exception $e) {
            Db::rollback();
            $failCount++;
            writeLog("订单 #{$order->order_id} 结算失败: " . $e->getMessage());
            Log::error('自动结算订单失败', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    writeLog("========== 结算完成 ==========");
    writeLog("成功: {$successCount} 个，失败: {$failCount} 个");

} catch (\Exception $e) {
    writeLog("执行异常: " . $e->getMessage());
    Log::error('自动结算任务异常', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
