<?php
namespace app\api\controller;

use app\common\service\PaymentService;
use think\facade\Request;

/**
 * 支付控制器
 */
class Payment extends Base
{
    /**
     * 微信支付下单
     */
    public function wechatPay()
    {
        $amount = Request::param('amount');
        $userId = $this->userId;
        
        if ($amount < 10) {
            return json(['code' => -1, 'msg' => '充值金额不能小于10元']);
        }
        
        $notifyUrl = Request::domain() . '/api/payment/wechat-notify';
        
        return json(PaymentService::wechatPay($userId, $amount, $notifyUrl));
    }
    
    /**
     * 支付宝支付下单
     */
    public function alipay()
    {
        $amount = Request::param('amount');
        $userId = $this->userId;
        
        if ($amount < 10) {
            return json(['code' => -1, 'msg' => '充值金额不能小于10元']);
        }
        
        $notifyUrl = Request::domain() . '/api/payment/alipay-notify';
        $returnUrl = Request::param('return_url', Request::domain() . '/recharge-success.html');
        
        return json(PaymentService::alipay($userId, $amount, $notifyUrl, $returnUrl));
    }
    
    /**
     * 获取USDT充值地址
     */
    public function getUsdtAddress()
    {
        $userId = $this->userId;
        
        $address = PaymentService::createUsdtAddress($userId);
        
        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'address' => $address,
                'network' => 'TRC20',
                'tips' => '请使用TRC20网络转账USDT到此地址，12个确认后自动到账'
            ]
        ]);
    }
    
    /**
     * 检查USDT到账
     */
    public function checkUsdtPayment()
    {
        $userId = $this->userId;
        
        return json(PaymentService::checkUsdtPayment($userId));
    }
    
    /**
     * 微信支付回调
     */
    public function wechatNotify()
    {
        $xmlData = file_get_contents('php://input');
        echo PaymentService::wechatNotify($xmlData);
    }
    
    /**
     * 支付宝回调
     */
    public function alipayNotify()
    {
        $postData = Request::post();
        echo PaymentService::alipayNotify($postData);
    }
}
