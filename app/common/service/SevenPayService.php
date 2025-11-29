<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Log;
use app\common\service\TelegramBotService;

/**
 * SevenPay支付服务
 * 文档: https://sevenpay.lh.plus/merchants/#/api-document
 */
class SevenPayService
{
    // 商户配置
    private static $config = [
        'merchantId' => 'M1763172182',
        'username' => 'XY8888',
        'merchantName' => 'XY',
        'apiKey' => '3e4cbfb35a35787ccc98bd13027b4a02',
        'payGateway' => 'https://sevenpay.lh.plus/api/pay/create',      // ✅ 修正 URL
        'queryGateway' => 'https://sevenpay.lh.plus/api/pay/query',     // ✅ 修正 URL
        'callbackIP' => '23.94.207.16',
        'channels' => [
            'wechat' => ['code' => 'S02', 'name' => '微信扫码', 'min' => 100, 'max' => 3000],
            'alipay' => ['code' => 'S01', 'name' => '支付宝小额原生', 'min' => 100, 'max' => 20000]
        ]
    ];

    /**
     * 创建支付订单
     */
    public static function createPayment($userId, $amount, $channel = 'wechat')
    {
        // 验证通道
        if (!isset(self::$config['channels'][$channel])) {
            return ['code' => -1, 'msg' => '无效的支付通道'];
        }

        $channelConfig = self::$config['channels'][$channel];

        // 验证金额范围
        if ($amount < $channelConfig['min'] || $amount > $channelConfig['max']) {
            return [
                'code' => -1,
                'msg' => "充值金额限制：{$channelConfig['min']}-{$channelConfig['max']}元"
            ];
        }

        // 生成订单号
        $orderNo = 'R' . strtoupper(substr($channel, 0, 2)) . date('YmdHis') . rand(1000, 9999);

        // 创建充值记录（保存用户真实IP）
        $rechargeId = Db::name('recharge_records')->insertGetId([
            'user_id' => $userId,
            'user_ip' => TelegramBotService::getRealIp(),  // 保存用户真实IP（处理CF代理）
            'order_no' => $orderNo,
            'amount' => $amount,
            'currency' => 'CNY',
            'payment_method' => $channel,
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 构建请求参数（遵循SevenPay文档规范）
        $domain = request()->domain();
        $params = [
            'merchantCode' => self::$config['merchantId'],
            'channelType' => $channelConfig['code'],
            'merchantOrderNo' => $orderNo,
            'amount' => number_format($amount, 2, '.', ''),
            'notifyUrl' => $domain . '/api/payment-v2/sevenpaynotify',  // 修正回调地址
            'returnUrl' => $domain . '/recharge-success.html',  // 支付成功后跳转地址
            'ip' => request()->ip(),  // 用户IP，不能是服务器IP
            'title' => 'Providence充值',
            'describe' => '用户充值'
        ];

        // 生成签名
        $params['sign'] = self::generateSign($params);

        // 发送请求
        $response = self::httpPost(self::$config['payGateway'], $params);

        Log::info('SevenPay创建订单', [
            'user_id' => $userId,
            'order_no' => $orderNo,
            'amount' => $amount,
            'channel' => $channel,
            'channel_code' => $channelConfig['code'],
            'params' => $params,
            'response' => $response
        ]);

        if (!$response) {
            Log::error('SevenPay请求失败', [
                'user_id' => $userId,
                'order_no' => $orderNo,
                'channel' => $channel,
                'gateway' => self::$config['payGateway']
            ]);
            return [
                'code' => -1,
                'msg' => '支付网关请求失败，请稍后重试'
            ];
        }

        if (isset($response['code']) && $response['code'] == 1) {
            // 发送Telegram通知（创建订单时）
            try {
                $user = Db::name('users')->where('id', $userId)->find();
                if ($user) {
                    TelegramBotService::notifyRecharge([
                        'username' => $user['username'],
                        'uid' => $user['uid']
                    ], [
                        'amount' => $amount,
                        'currency' => 'CNY',
                        'payment_method' => $channelConfig['name'],
                        'order_no' => $orderNo,
                        'status' => 0, // 待支付
                        'ip' => request()->ip()
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('充值订单创建通知失败', ['error' => $e->getMessage()]);
            }

            // 遵循SevenPay官方文档规范：返回驼峰命名
            return [
                'code' => 1,
                'msg' => '创建成功',
                'data' => [
                    'orderNo' => $response['data']['orderNo'] ?? $orderNo,  // 系统订单号（驼峰）
                    'merchantOrderNo' => $orderNo,  // 商户订单号
                    'recharge_id' => $rechargeId,
                    'payUrl' => $response['data']['payUrl'] ?? '',  // 支付链接（驼峰）
                    'expireTime' => $response['data']['expireTime'] ?? (time() + 1800),  // 过期时间（10位时间戳）
                    'amount' => (float)$amount,
                    'channel' => $channelConfig['name']
                ]
            ];
        }

        // 记录详细错误信息
        $errorMsg = $response['msg'] ?? $response['message'] ?? $response['error'] ?? '创建订单失败';

        // 如果是"无可用通道"错误，提供更友好的提示
        if (strpos($errorMsg, '无可用通道') !== false || strpos($errorMsg, '通道') !== false) {
            $errorMsg = '支付宝通道暂时不可用，请稍后重试或选择其他支付方式';
        }

        Log::error('SevenPay创建订单失败', [
            'user_id' => $userId,
            'order_no' => $orderNo,
            'channel' => $channel,
            'channel_code' => $channelConfig['code'],
            'response' => $response,
            'error_msg' => $errorMsg,
            'request_params' => $params
        ]);

        return [
            'code' => -1,
            'msg' => $errorMsg
        ];
    }

    /**
     * 支付回调处理
     */
    public static function handleNotify($postData)
    {
        Log::info('SevenPay支付回调', $postData);

        // 验证IP
        $clientIP = request()->ip();
        if ($clientIP !== self::$config['callbackIP']) {
            Log::warning('SevenPay回调IP不匹配', ['client_ip' => $clientIP]);
        }

        // 验证签名
        if (!self::verifySign($postData)) {
            Log::error('SevenPay回调签名验证失败', $postData);
            return 'SIGN_ERROR';
        }

        // 根据文档：回调参数使用下划线命名
        $merchantOrderNo = $postData['merchant_order_no'] ?? $postData['merchantOrderNo'] ?? '';
        $systemOrderNo = $postData['order_no'] ?? $postData['orderNo'] ?? '';
        $amount = $postData['amount'] ?? 0;
        $payAmount = $postData['pay_amount'] ?? $postData['payAmount'] ?? $amount;
        $status = $postData['status'] ?? 0; // 1=支付成功, 2=支付失败
        $paymentTime = $postData['payment_time'] ?? $postData['paymentTime'] ?? time();

        if ($status != 1) {
            Log::warning('支付状态不是成功', ['status' => $status, 'merchant_order_no' => $merchantOrderNo]);
            return 'STATUS_ERROR';
        }

        // 查询订单（使用商户订单号）
        $recharge = Db::name('recharge_records')->where('order_no', $merchantOrderNo)->find();

        if (!$recharge) {
            Log::error('订单不存在', ['merchant_order_no' => $merchantOrderNo]);
            return 'ORDER_NOT_FOUND';
        }

        if ($recharge['status'] == 1) {
            return 'SUCCESS'; // 已处理
        }

        // 处理充值
        Db::startTrans();
        try {
            // 更新订单状态
            Db::name('recharge_records')->where('order_no', $merchantOrderNo)->update([
                'status' => 1,
                'transaction_id' => $systemOrderNo,  // 系统订单号
                'paid_at' => date('Y-m-d H:i:s', $paymentTime),
                'paid_amount' => $payAmount  // 实际支付金额
            ]);

            // 更新用户余额（使用实际支付金额）
            Db::name('users')->where('id', $recharge['user_id'])->inc('balance_cny', $payAmount)->update();
            Db::name('users')->where('id', $recharge['user_id'])->inc('total_recharge_cny', $payAmount)->update();

            // 记录钱包日志
            $balanceAfter = Db::name('users')->where('id', $recharge['user_id'])->value('balance_cny');
            Db::name('wallet_logs')->insert([
                'user_id' => $recharge['user_id'],
                'type' => 'recharge',
                'currency' => 'CNY',
                'amount' => $payAmount,  // 使用实际支付金额
                'balance_after' => $balanceAfter,
                'remark' => '充值到账',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();

            Log::info('充值成功', [
                'user_id' => $recharge['user_id'],
                'merchant_order_no' => $merchantOrderNo,
                'system_order_no' => $systemOrderNo,
                'amount' => $amount,
                'pay_amount' => $payAmount
            ]);

            // 发送Telegram通知
            try {
                Log::info('准备发送充值成功通知', [
                    'user_id' => $recharge['user_id'],
                    'order_no' => $merchantOrderNo
                ]);

                $user = Db::name('users')->where('id', $recharge['user_id'])->find();
                if ($user) {
                    Log::info('找到用户，准备发送通知', [
                        'username' => $user['username'],
                        'uid' => $user['uid'] ?? 'N/A'
                    ]);

                    $result = TelegramBotService::notifyRecharge([
                        'username' => $user['username'],
                        'uid' => $user['uid'] ?? $user['id']  // 如果uid不存在，使用id
                    ], [
                        'amount' => $payAmount,  // 使用实际支付金额
                        'currency' => 'CNY',
                        'payment_method' => $recharge['payment_method'],
                        'order_no' => $merchantOrderNo,  // 商户订单号
                        'system_order_no' => $systemOrderNo,  // 系统订单号
                        'status' => 1,
                        'ip' => $recharge['user_ip'] ?? request()->ip()
                    ]);

                    if ($result) {
                        Log::info('充值成功通知发送成功', [
                            'user_id' => $recharge['user_id'],
                            'order_no' => $merchantOrderNo
                        ]);
                    } else {
                        Log::warning('充值成功通知发送失败', [
                            'user_id' => $recharge['user_id'],
                            'order_no' => $merchantOrderNo
                        ]);
                    }
                } else {
                    Log::warning('充值成功通知失败：用户不存在', ['user_id' => $recharge['user_id']]);
                }
            } catch (\Exception $e) {
                Log::error('充值成功通知异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $recharge['user_id'],
                    'order_no' => $merchantOrderNo
                ]);
            }

            return 'SUCCESS';
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('充值处理失败: ' . $e->getMessage());
            return 'ERROR';
        }
    }

    /**
     * 生成签名
     */
    private static function generateSign($params)
    {
        // 1. 排除sign字段
        unset($params['sign']);

        // 2. 按key排序
        ksort($params);

        // 3. 拼接成字符串
        $signStr = '';
        foreach ($params as $key => $val) {
            if ($val !== '' && $val !== null) {
                $signStr .= $key . '=' . $val . '&';
            }
        }

        // 4. 加上密钥
        $signStr .= 'key=' . self::$config['apiKey'];

        // 5. MD5加密（小写）
        return strtolower(md5($signStr));
    }

    /**
     * 验证签名
     */
    private static function verifySign($data)
    {
        $sign = $data['sign'] ?? '';
        $calculatedSign = self::generateSign($data);

        return $sign === $calculatedSign;
    }

    /**
     * HTTP POST请求
     */
    private static function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('SevenPay CURL错误', [
                'url' => $url,
                'error' => $curlError,
                'http_code' => $httpCode
            ]);
            return null;
        }

        if ($httpCode == 200) {
            $decoded = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('SevenPay JSON解析失败', [
                    'url' => $url,
                    'raw_response' => $result,
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }
            return $decoded;
        }

        Log::error('SevenPay HTTP请求失败', [
            'url' => $url,
            'http_code' => $httpCode,
            'response' => $result
        ]);

        return null;
    }
}
