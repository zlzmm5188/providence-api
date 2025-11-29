<?php
/**
 * 币种兑换控制器
 */

namespace app\api\controller;

use app\common\middleware\AuthMiddleware;
use app\common\model\User;
use app\common\model\WalletLog;
use think\facade\Db;

class Currency
{
    /**
     * 人民币兑换USDT
     * POST /api/currency/exchange-cny-to-usdt
     */
    public function exchangeCnyToUsdt()
    {
        $userId = request()->userId;
        $amount = input('post.amount', '0', 'trim');
        $rate = input('post.rate', '0', 'trim'); // 汇率（可选，不传则使用实时汇率）

        if (bccomp($amount, '0', 8) <= 0) {
            return json(['code' => -1, 'msg' => '兑换金额必须大于0', 'data' => null]);
        }

        try {
            // 获取实时汇率（如果未提供）
            if (bccomp($rate, '0', 8) <= 0) {
                $rate = $this->getUsdtRate();
            }

            if (bccomp($rate, '0', 8) <= 0) {
                return json(['code' => -1, 'msg' => '获取汇率失败', 'data' => null]);
            }

            // 计算USDT数量
            $usdtAmount = bcdiv($amount, $rate, 8);

            $user = User::find($userId);
            if (!$user) {
                return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
            }

            $cnyBalance = $user->balance_cny ?? 0;
            if (bccomp($cnyBalance, $amount, 8) < 0) {
                return json(['code' => -1, 'msg' => 'CNY余额不足', 'data' => null]);
            }

            Db::startTrans();
            try {
                // 扣除CNY
                $user->balance_cny = bcsub($cnyBalance, $amount, 8);

                // 增加USDT
                $usdtBalance = $user->balance_usdt ?? 0;
                $user->balance_usdt = bcadd($usdtBalance, $usdtAmount, 8);
                $user->save();

                // 记录CNY变动
                WalletLog::create([
                    'user_id' => $userId,
                    'type' => 'currency_exchange',
                    'amount' => -$amount,
                    'currency' => 'CNY',
                    'balance_before' => $cnyBalance,
                    'balance_after' => $user->balance_cny,
                    'remark' => "兑换USDT，汇率：{$rate}",
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // 记录USDT变动
                WalletLog::create([
                    'user_id' => $userId,
                    'type' => 'currency_exchange',
                    'amount' => $usdtAmount,
                    'currency' => 'USDT',
                    'balance_before' => $usdtBalance,
                    'balance_after' => $user->balance_usdt,
                    'remark' => "CNY兑换，汇率：{$rate}",
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                Db::commit();

                return json([
                    'code' => 1,
                    'msg' => '兑换成功',
                    'data' => [
                        'cny_amount' => $amount,
                        'usdt_amount' => $usdtAmount,
                        'rate' => $rate,
                        'balance_cny' => $user->balance_cny,
                        'balance_usdt' => $user->balance_usdt
                    ]
                ]);
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '兑换失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取USDT实时汇率
     * @return string
     */
    private function getUsdtRate()
    {
        // 优先从缓存获取
        $rate = cache('usdt_rate');
        if ($rate) {
            return $rate;
        }

        // 从数据库获取最新汇率
        $rateRecord = Db::name('usdt_rate')
            ->order('id', 'desc')
            ->find();

        if ($rateRecord) {
            $rate = $rateRecord['price'];
            // 缓存5分钟
            cache('usdt_rate', $rate, 300);
            return $rate;
        }

        // 默认汇率（如果都没有，返回7.2）
        return '7.2';
    }

    /**
     * 获取USDT汇率API
     * GET /api/currency/get-usdt-rate
     */
    public function getRate()
    {
        $rate = $this->getUsdtRate();
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'rate' => $rate,
                'price' => $rate
            ]
        ]);
    }
}
