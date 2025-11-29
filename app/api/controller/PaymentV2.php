<?php
namespace app\api\controller;

use app\common\service\SevenPayService;
use app\common\service\UsdtService;
use app\common\service\TelegramBotService;
use think\facade\Request;
use think\facade\Db;
use think\facade\Filesystem;

/**
 * 支付控制器V2（SevenPay + USDT + 银行卡）
 */
class PaymentV2 extends Base
{
    /**
     * 获取银行卡收款信息
     */
    public function bankConfig()
    {
        // 从系统配置获取收款银行卡信息
        $bankInfo = [
            'bank_name' => env('BANK_NAME', '中国工商银行'),
            'account_name' => env('BANK_ACCOUNT_NAME', '***公司'),
            'account_no' => env('BANK_ACCOUNT_NO', '6222 **** **** 1234'),
            'branch' => env('BANK_BRANCH', '上海分行'),
            'min_amount' => 100,
            'max_amount' => 50000,
            'tips' => [
                '请使用本人银行卡转账',
                '转账后请上传凭证截图',
                '系统会在1-30分钟内审核到账'
            ]
        ];

        return json([
            'code' => 1,
            'msg' => 'ok',
            'data' => $bankInfo
        ]);
    }

    /**
     * 创建银行卡充值订单（上传凭证）
     */
    public function bankCreate()
    {
        $amount = Request::param('amount');
        $voucher = Request::file('voucher');  // 上传的凭证图片

        if (!$amount || $amount <= 0) {
            return json(['code' => -1, 'msg' => '请输入充值金额']);
        }

        if ($amount < 100) {
            return json(['code' => -1, 'msg' => '充值金额不能小于100元']);
        }

        if ($amount > 50000) {
            return json(['code' => -1, 'msg' => '单笔充值金额不能超过50000元']);
        }

        // 检查是否有未完成的银行卡订单（任何待审核订单都不允许再创建）
        $pendingOrder = Db::name('recharge_records')
            ->where('user_id', $this->userId)
            ->where('payment_method', 'bank')
            ->where('status', 0)  // 待审核状态
            ->order('created_at', 'desc')
            ->find();

        if ($pendingOrder) {
            return json([
                'code' => -1,
                'msg' => '您有未完成的银行卡充值订单，请等待审核完成或联系客服取消后重试',
                'data' => [
                    'pending_order' => [
                        'id' => $pendingOrder['id'],
                        'order_no' => $pendingOrder['order_no'],
                        'amount' => $pendingOrder['amount'],
                        'created_at' => $pendingOrder['created_at']
                    ]
                ]
            ]);
        }

        // 处理凭证上传
        $voucherPath = '';
        if ($voucher) {
            try {
                // 验证文件
                validate(['voucher' => [
                    'fileSize' => 5 * 1024 * 1024,  // 5MB
                    'fileExt' => 'jpg,jpeg,png,gif',
                    'fileMime' => 'image/jpeg,image/png,image/gif'
                ]])->check(['voucher' => $voucher]);

                // 保存文件
                $saveName = Filesystem::disk('public')->putFile('vouchers', $voucher);
                $voucherPath = '/storage/' . $saveName;
            } catch (\Exception $e) {
                return json(['code' => -1, 'msg' => '凭证上传失败: ' . $e->getMessage()]);
            }
        }

        // 生成订单号
        $orderNo = 'RBANK' . date('YmdHis') . rand(1000, 9999);

        // 获取用户真实IP
        $userIp = TelegramBotService::getRealIp();

        // 创建充值记录
        $rechargeId = Db::name('recharge_records')->insertGetId([
            'user_id' => $this->userId,
            'user_ip' => $userIp,
            'order_no' => $orderNo,
            'amount' => $amount,
            'currency' => 'CNY',
            'payment_method' => 'bank',
            'voucher_image' => $voucherPath,
            'status' => 0,  // 待审核
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // 发送Telegram通知
        try {
            $user = Db::name('users')->where('id', $this->userId)->find();
            if ($user) {
                TelegramBotService::notifyRechargeCreated([
                    'username' => $user['username'],
                    'uid' => $user['uid'] ?? $user['id']
                ], [
                    'amount' => $amount,
                    'currency' => 'CNY',
                    'payment_method' => 'bank',
                    'order_no' => $orderNo,
                    'ip' => $userIp
                ]);
            }
        } catch (\Exception $e) {
            // 通知失败不影响订单创建
        }

        return json([
            'code' => 1,
            'msg' => '订单创建成功，请等待审核',
            'data' => [
                'orderNo' => $orderNo,
                'order_no' => $orderNo,
                'rechargeId' => $rechargeId,
                'amount' => (float)$amount,
                'status' => 0,
                'voucher' => $voucherPath,
                'message' => '凭证已提交，请等待客服审核'
            ]
        ]);
    }

    /**
     * 补传银行卡充值凭证
     */
    public function bankUploadVoucher()
    {
        $orderNo = Request::param('order_no');
        $voucher = Request::file('voucher');

        if (!$orderNo) {
            return json(['code' => -1, 'msg' => '缺少订单号']);
        }

        if (!$voucher) {
            return json(['code' => -1, 'msg' => '请上传凭证图片']);
        }

        // 查找订单
        $order = Db::name('recharge_records')
            ->where('order_no', $orderNo)
            ->where('user_id', $this->userId)
            ->where('payment_method', 'bank')
            ->find();

        if (!$order) {
            return json(['code' => -1, 'msg' => '订单不存在']);
        }

        if ($order['status'] == 1) {
            return json(['code' => -1, 'msg' => '订单已完成']);
        }

        // 处理凭证上传
        try {
            validate(['voucher' => [
                'fileSize' => 5 * 1024 * 1024,
                'fileExt' => 'jpg,jpeg,png,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif'
            ]])->check(['voucher' => $voucher]);

            $saveName = Filesystem::disk('public')->putFile('vouchers', $voucher);
            $voucherPath = '/storage/' . $saveName;

            // 更新订单
            Db::name('recharge_records')
                ->where('id', $order['id'])
                ->update([
                    'voucher_image' => $voucherPath,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            return json([
                'code' => 1,
                'msg' => '凭证上传成功',
                'data' => [
                    'voucher' => $voucherPath
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '上传失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 取消银行卡订单
     */
    public function bankCancel()
    {
        $orderNo = Request::param('order_no');

        if (!$orderNo) {
            return json(['code' => -1, 'msg' => '缺少订单号']);
        }

        $order = Db::name('recharge_records')
            ->where('order_no', $orderNo)
            ->where('user_id', $this->userId)
            ->where('payment_method', 'bank')
            ->find();

        if (!$order) {
            return json(['code' => -1, 'msg' => '订单不存在']);
        }

        if ($order['status'] == 1) {
            return json(['code' => -1, 'msg' => '订单已完成，无法取消']);
        }

        if ($order['status'] == -1) {
            return json(['code' => -1, 'msg' => '订单已取消']);
        }

        Db::name('recharge_records')
            ->where('id', $order['id'])
            ->update([
                'status' => -1,
                'remark' => '用户主动取消'
            ]);

        return json([
            'code' => 1,
            'msg' => '订单已取消'
        ]);
    }

    /**
     * 微信支付（SevenPay）
     */
    public function wechat()
    {
        $amount = Request::param('amount');

        if (!$amount || $amount <= 0) {
            return json(['code' => -1, 'msg' => '请输入充值金额']);
        }

        if ($amount < 100) {
            return json(['code' => -1, 'msg' => '充值金额不能小于100元']);
        }

        $result = SevenPayService::createPayment($this->userId, $amount, 'wechat');

        // 统一返回格式，遵循SevenPay官方文档规范
        if ($result && isset($result['code']) && $result['code'] == 1) {
            // 优先返回商户订单号（我们数据库中的订单号），这是前端需要用来查询的
            $merchantOrderNo = $result['data']['merchantOrderNo'] ?? '';
            $systemOrderNo = $result['data']['orderNo'] ?? '';  // SevenPay系统订单号

            return json([
                'code' => 1,
                'msg' => 'ok',
                'data' => [
                    'orderNo' => $merchantOrderNo,  // 商户订单号（用于查询）
                    'merchantOrderNo' => $merchantOrderNo,  // 商户订单号（明确标识）
                    'systemOrderNo' => $systemOrderNo,  // 系统订单号（SevenPay返回）
                    'rechargeId' => $result['data']['recharge_id'] ?? 0,  // 充值记录ID
                    'payUrl' => $result['data']['payUrl'] ?? '',
                    'expireTime' => $result['data']['expireTime'] ?? (time() + 1800),
                    'amount' => (float)$amount,
                    'status' => 0  // 0=待支付
                ]
            ]);
        }

        return json($result ?: ['code' => -1, 'msg' => '创建订单失败']);
    }

    /**
     * 支付宝支付（SevenPay）
     */
    public function alipay()
    {
        $amount = Request::param('amount');

        if (!$amount || $amount <= 0) {
            return json(['code' => -1, 'msg' => '请输入充值金额']);
        }

        if ($amount < 100) {
            return json(['code' => -1, 'msg' => '充值金额不能小于100元']);
        }

        $result = SevenPayService::createPayment($this->userId, $amount, 'alipay');

        // 统一返回格式，遵循SevenPay官方文档规范
        if ($result && isset($result['code']) && $result['code'] == 1) {
            // 优先返回商户订单号（我们数据库中的订单号），这是前端需要用来查询的
            $merchantOrderNo = $result['data']['merchantOrderNo'] ?? '';
            $systemOrderNo = $result['data']['orderNo'] ?? '';  // SevenPay系统订单号

            return json([
                'code' => 1,
                'msg' => 'ok',
                'data' => [
                    'orderNo' => $merchantOrderNo,  // 商户订单号（用于查询）
                    'merchantOrderNo' => $merchantOrderNo,  // 商户订单号（明确标识）
                    'systemOrderNo' => $systemOrderNo,  // 系统订单号（SevenPay返回）
                    'rechargeId' => $result['data']['recharge_id'] ?? 0,  // 充值记录ID
                    'payUrl' => $result['data']['payUrl'] ?? '',
                    'expireTime' => $result['data']['expireTime'] ?? (time() + 1800),
                    'amount' => (float)$amount,
                    'status' => 0  // 0=待支付
                ]
            ]);
        }

        return json($result ?: ['code' => -1, 'msg' => '创建订单失败']);
    }

    /**
     * 手动触发USDT监控（用于测试或手动检查）
     * GET /pay/usdt-monitor
     */
    public function usdtMonitor()
    {
        // 只有管理员可以手动触发监控
        // 这里可以添加权限检查，暂时允许所有登录用户触发

        try {
            $result = UsdtService::monitorTransactions();

            if ($result) {
                return json([
                    'code' => 1,
                    'msg' => '监控执行成功',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                return json([
                    'code' => -1,
                    'msg' => '监控执行失败，请查看日志',
                    'data' => []
                ]);
            }
        } catch (\Exception $e) {
            return json([
                'code' => -1,
                'msg' => '监控执行异常: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }

    /**
     * USDT充值配置
     */
    public function usdtConfig()
    {
        // 使用 UsdtService 获取真实配置
        $config = UsdtService::getUsdtConfig();

        return success('获取成功', [
            'usdt_address' => $config['address'],
            'min_amount' => (string)$config['min_amount'],
            'max_amount' => (string)$config['max_amount'],
            'rate' => (string)$config['rate'],
            'network' => $config['network'],
            'tips' => $config['tips']
        ]);
    }

    /**
     * 创建USDT充值订单
     */
    public function usdtCreate()
    {
        $amount = Request::param('amount');

        if (!$amount || $amount <= 0) {
            return json(['code' => -1, 'msg' => '请输入充值金额']);
        }

        $result = UsdtService::createUsdtOrder($this->userId, $amount);

        // 统一返回格式
        if ($result && isset($result['code']) && $result['code'] == 1) {
            return json([
                'code' => 1,
                'msg' => 'ok',
                'data' => [
                    'orderNo' => $result['data']['order_no'] ?? '',  // 订单号（驼峰，兼容前端）
                    'order_no' => $result['data']['order_no'] ?? '',  // 订单号（下划线，兼容旧版）
                    'rechargeId' => $result['data']['recharge_id'] ?? 0,  // 充值记录ID
                    'amount' => (float)$amount,
                    'status' => 0,  // 0=待支付
                    'payUrl' => $result['data']['address'] ?? '',  // USDT地址作为payUrl
                    'address' => $result['data']['address'] ?? '',
                    'network' => $result['data']['network'] ?? 'TRC20',
                    'rate' => (float)($result['data']['rate'] ?? 7.2),
                    'cnyAmount' => (float)($result['data']['cny_amount'] ?? ($amount * 7.2)),
                    'cny_amount' => (float)($result['data']['cny_amount'] ?? ($amount * 7.2))  // 兼容旧版
                ]
            ]);
        }

        return json($result ?: ['code' => -1, 'msg' => '创建订单失败']);
    }

    /**
     * 取消USDT订单
     */
    public function usdtCancel()
    {
        $orderNo = Request::param('order_no');

        if (!$orderNo) {
            return json(['code' => -1, 'msg' => '缺少订单号']);
        }

        $result = UsdtService::cancelOrder($this->userId, $orderNo);
        return json($result);
    }

    /**
     * 查询USDT订单状态
     */
    public function usdtCheck()
    {
        $orderNo = Request::param('order_no');

        if (!$orderNo) {
            return json(['code' => -1, 'msg' => '缺少订单号']);
        }

        $result = UsdtService::checkOrder($orderNo);

        // 统一返回格式：status=1表示已支付
        if ($result && isset($result['code']) && $result['code'] == 1) {
            return json([
                'code' => 1,
                'msg' => 'ok',
                'data' => [
                    'orderNo' => $result['data']['order_no'] ?? $orderNo,  // 订单号（驼峰）
                    'order_no' => $result['data']['order_no'] ?? $orderNo,  // 订单号（下划线，兼容）
                    'amount' => (float)($result['data']['amount'] ?? 0),
                    'status' => (int)($result['data']['status'] ?? 0),  // 0=待支付, 1=已支付
                    'paidAt' => $result['data']['paid_at'] ?? null,  // 支付时间
                    'paid_at' => $result['data']['paid_at'] ?? null  // 兼容旧版
                ]
            ]);
        }

        return json($result ?: ['code' => -1, 'msg' => '查询失败']);
    }

    /**
     * SevenPay支付回调
     * 文档要求：收到通知后必须返回小写 "success"
     */
    public function sevenPayNotify()
    {
        $postData = Request::post();

        $result = SevenPayService::handleNotify($postData);

        // 根据文档要求：收到通知后必须返回小写 "success"
        if ($result === 'SUCCESS') {
            echo 'success';  // 小写 success
        } else {
            echo $result;  // 其他错误返回原值
        }
    }
}
