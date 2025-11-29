<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 支付服务类
 * 处理微信、支付宝、USDT充值
 */
class PaymentService
{
    /**
     * 创建充值订单
     */
    public static function createRechargeOrder($userId, $amount, $currency, $paymentMethod)
    {
        // 生成唯一订单号
        $orderNo = self::generateOrderNo($paymentMethod);
        
        // 创建充值记录
        $rechargeId = Db::name('recharge_records')->insertGetId([
            'user_id' => $userId,
            'order_no' => $orderNo,
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'status' => 0, // 0=待支付
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return [
            'recharge_id' => $rechargeId,
            'order_no' => $orderNo
        ];
    }
    
    /**
     * 生成订单号
     * 格式：R + 支付方式前缀 + 时间戳 + 随机数
     */
    private static function generateOrderNo($paymentMethod)
    {
        $prefix = [
            'wechat' => 'WX',
            'alipay' => 'ALI',
            'usdt' => 'USDT'
        ];
        
        return 'R' . ($prefix[$paymentMethod] ?? 'PAY') 
            . date('YmdHis') 
            . rand(1000, 9999);
    }
    
    /**
     * 微信支付下单
     */
    public static function wechatPay($userId, $amount, $notifyUrl)
    {
        // 创建订单
        $order = self::createRechargeOrder($userId, $amount, 'CNY', 'wechat');
        
        // 微信支付参数
        $params = [
            'appid' => env('WECHAT_APPID'),
            'mch_id' => env('WECHAT_MCH_ID'),
            'nonce_str' => self::getNonceStr(),
            'body' => 'Providence-充值',
            'out_trade_no' => $order['order_no'],
            'total_fee' => $amount * 100, // 单位：分
            'spbill_create_ip' => request()->ip(),
            'notify_url' => $notifyUrl,
            'trade_type' => 'NATIVE' // 扫码支付
        ];
        
        // 生成签名
        $params['sign'] = self::wechatSign($params);
        
        // 调用微信统一下单API
        $xml = self::arrayToXml($params);
        $response = self::httpPost('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        $result = self::xmlToArray($response);
        
        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return [
                'code' => 1,
                'msg' => '创建成功',
                'data' => [
                    'order_no' => $order['order_no'],
                    'qrcode' => $result['code_url'], // 二维码链接
                    'amount' => $amount
                ]
            ];
        }
        
        return [
            'code' => -1,
            'msg' => $result['err_code_des'] ?? '创建失败'
        ];
    }
    
    /**
     * 支付宝支付下单
     */
    public static function alipay($userId, $amount, $notifyUrl, $returnUrl)
    {
        // 创建订单
        $order = self::createRechargeOrder($userId, $amount, 'CNY', 'alipay');
        
        require_once app()->getRootPath() . 'vendor/alipay/alipay-sdk-php/AopSdk.php';
        
        $aop = new \AopClient();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = env('ALIPAY_APPID');
        $aop->rsaPrivateKey = env('ALIPAY_PRIVATE_KEY');
        $aop->alipayrsaPublicKey = env('ALIPAY_PUBLIC_KEY');
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'UTF-8';
        $aop->format = 'json';
        
        $request = new \AlipayTradePagePayRequest();
        $request->setReturnUrl($returnUrl);
        $request->setNotifyUrl($notifyUrl);
        
        $bizContent = [
            'out_trade_no' => $order['order_no'],
            'total_amount' => $amount,
            'subject' => 'Providence充值',
            'product_code' => 'FAST_INSTANT_TRADE_PAY'
        ];
        $request->setBizContent(json_encode($bizContent));
        
        $result = $aop->pageExecute($request);
        
        return [
            'code' => 1,
            'msg' => '创建成功',
            'data' => [
                'order_no' => $order['order_no'],
                'form' => $result, // HTML表单
                'amount' => $amount
            ]
        ];
    }
    
    /**
     * USDT充值地址生成
     */
    public static function createUsdtAddress($userId)
    {
        // 检查用户是否已有USDT地址
        $address = Db::name('user_usdt_addresses')->where('user_id', $userId)->find();
        
        if (!$address) {
            // 调用USDT地址生成API（需要对接第三方服务）
            $newAddress = self::generateUsdtAddress();
            
            Db::name('user_usdt_addresses')->insert([
                'user_id' => $userId,
                'address' => $newAddress['address'],
                'private_key' => self::encrypt($newAddress['private_key']),
                'network' => 'TRC20', // 或 ERC20
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $newAddress['address'];
        }
        
        return $address['address'];
    }
    
    /**
     * USDT自动到账监控
     */
    public static function checkUsdtPayment($userId)
    {
        $address = Db::name('user_usdt_addresses')
            ->where('user_id', $userId)
            ->value('address');
        
        if (!$address) {
            return ['code' => -1, 'msg' => '未找到充值地址'];
        }
        
        // 调用区块链浏览器API查询交易
        $transactions = self::queryUsdtTransactions($address);
        
        foreach ($transactions as $tx) {
            // 检查是否已处理
            $exists = Db::name('recharge_records')
                ->where('transaction_hash', $tx['hash'])
                ->find();
            
            if (!$exists && $tx['confirmations'] >= 12) {
                // 创建充值记录并到账
                self::processUsdtRecharge($userId, $tx);
            }
        }
        
        return ['code' => 1, 'msg' => '检查完成'];
    }
    
    /**
     * 处理USDT充值到账
     */
    private static function processUsdtRecharge($userId, $transaction)
    {
        Db::startTrans();
        try {
            // 创建充值记录
            $rechargeId = Db::name('recharge_records')->insertGetId([
                'user_id' => $userId,
                'order_no' => 'RUSDT' . date('YmdHis') . rand(1000, 9999),
                'amount' => $transaction['amount'],
                'currency' => 'USDT',
                'payment_method' => 'usdt',
                'transaction_hash' => $transaction['hash'],
                'status' => 1, // 已到账
                'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // 更新用户余额
            Db::name('users')->where('id', $userId)->inc('balance_usdt', $transaction['amount'])->update();
            Db::name('users')->where('id', $userId)->inc('total_recharge_usdt', $transaction['amount'])->update();
            
            // 记录钱包日志
            Db::name('wallet_logs')->insert([
                'user_id' => $userId,
                'type' => 'recharge',
                'currency' => 'USDT',
                'amount' => $transaction['amount'],
                'balance_after' => Db::name('users')->where('id', $userId)->value('balance_usdt'),
                'remark' => 'USDT充值到账',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            
            Log::info('USDT充值到账', [
                'user_id' => $userId,
                'amount' => $transaction['amount'],
                'hash' => $transaction['hash']
            ]);
            
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('USDT充值处理失败: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 微信支付回调处理
     */
    public static function wechatNotify($xmlData)
    {
        $data = self::xmlToArray($xmlData);
        
        // 验证签名
        if (!self::verifyWechatSign($data)) {
            return self::xmlResponse(false, 'FAIL', '签名验证失败');
        }
        
        if ($data['return_code'] == 'SUCCESS' && $data['result_code'] == 'SUCCESS') {
            // 处理订单
            $orderNo = $data['out_trade_no'];
            $amount = $data['total_fee'] / 100;
            
            self::processPaymentSuccess($orderNo, $amount, 'wechat', $data['transaction_id']);
            
            return self::xmlResponse(true, 'SUCCESS', 'OK');
        }
        
        return self::xmlResponse(false, 'FAIL', '支付失败');
    }
    
    /**
     * 支付宝回调处理
     */
    public static function alipayNotify($postData)
    {
        require_once app()->getRootPath() . 'vendor/alipay/alipay-sdk-php/AopSdk.php';
        
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = env('ALIPAY_PUBLIC_KEY');
        
        $result = $aop->rsaCheckV1($postData, null, 'RSA2');
        
        if ($result && $postData['trade_status'] == 'TRADE_SUCCESS') {
            $orderNo = $postData['out_trade_no'];
            $amount = $postData['total_amount'];
            
            self::processPaymentSuccess($orderNo, $amount, 'alipay', $postData['trade_no']);
            
            return 'success';
        }
        
        return 'fail';
    }
    
    /**
     * 处理支付成功
     */
    private static function processPaymentSuccess($orderNo, $amount, $paymentMethod, $transactionId)
    {
        Db::startTrans();
        try {
            // 更新充值记录
            $recharge = Db::name('recharge_records')->where('order_no', $orderNo)->find();
            
            if (!$recharge || $recharge['status'] == 1) {
                // 订单不存在或已处理
                Db::rollback();
                return false;
            }
            
            Db::name('recharge_records')->where('order_no', $orderNo)->update([
                'status' => 1,
                'transaction_id' => $transactionId,
                'paid_at' => date('Y-m-d H:i:s')
            ]);
            
            // 更新用户余额
            $currency = $recharge['currency'];
            $balanceField = $currency == 'CNY' ? 'balance_cny' : 'balance_usdt';
            $totalField = $currency == 'CNY' ? 'total_recharge_cny' : 'total_recharge_usdt';
            
            Db::name('users')->where('id', $recharge['user_id'])->inc($balanceField, $amount)->update();
            Db::name('users')->where('id', $recharge['user_id'])->inc($totalField, $amount)->update();
            
            // 记录钱包日志
            Db::name('wallet_logs')->insert([
                'user_id' => $recharge['user_id'],
                'type' => 'recharge',
                'currency' => $currency,
                'amount' => $amount,
                'balance_after' => Db::name('users')->where('id', $recharge['user_id'])->value($balanceField),
                'remark' => $paymentMethod . '充值到账',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('支付处理失败: ' . $e->getMessage());
            return false;
        }
    }
    
    // ========== 辅助方法 ==========
    
    private static function getNonceStr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }
    
    private static function wechatSign($params)
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            if ($v != '' && $k != 'sign') {
                $string .= $k . '=' . $v . '&';
            }
        }
        $string .= 'key=' . env('WECHAT_KEY');
        return strtoupper(md5($string));
    }
    
    private static function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            $xml .= '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
        }
        $xml .= '</xml>';
        return $xml;
    }
    
    private static function xmlToArray($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }
    
    private static function xmlResponse($success, $code, $msg)
    {
        return '<xml><return_code><![CDATA[' . $code . ']]></return_code><return_msg><![CDATA[' . $msg . ']]></return_msg></xml>';
    }
    
    private static function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    private static function encrypt($data)
    {
        // 使用环境变量中的密钥加密
        return openssl_encrypt($data, 'AES-256-CBC', env('ENCRYPT_KEY'), 0, substr(env('ENCRYPT_KEY'), 0, 16));
    }
    
    private static function generateUsdtAddress()
    {
        // TODO: 对接USDT地址生成服务
        // 可使用 Tronex、TronWeb 等库生成TRC20地址
        // 或对接第三方API如：Tatum、Infura等
        return [
            'address' => 'T' . bin2hex(random_bytes(20)),
            'private_key' => bin2hex(random_bytes(32))
        ];
    }
    
    private static function queryUsdtTransactions($address)
    {
        // TODO: 查询USDT交易记录
        // TRC20: https://api.trongrid.io/v1/accounts/{address}/transactions/trc20
        // ERC20: https://api.etherscan.io/api?module=account&action=tokentx
        return [];
    }
}
