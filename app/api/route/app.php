<?php
/**
 * API 应用路由配置
 * 路径: /app/api/route/app.php
 *
 * ThinkPHP 多应用模式会自动加载此文件
 */

use think\facade\Route;

// ========== 无需登录的接口 ==========
Route::group('api', function () {

    // 认证
    Route::post('auth/register', 'app\api\controller\Auth@register');
    Route::post('auth/login', 'app\api\controller\Auth@login');  // 管理员登录（后台专用）
    Route::post('user/login', 'app\api\controller\User@login');  // 用户登录（前台专用）
    Route::get('auth/codes', 'app\api\controller\Auth@codes');  // 权限代码

    // 安装（创建表，仅本地调用）
    Route::get('install/create-sign-tables', 'app\api\controller\Install@createSignTables');

    // 用户信息（自动识别用户/管理员）
    Route::get('user/info', 'app\api\controller\User@info');

    // 签到（获取信息，未登录也可查看）
    Route::get('user/sign/info', 'app\api\controller\Sign@info');

    // 支付配置（公开，无需认证）- 放在前面确保优先匹配
    Route::get('payment-v2/usdtconfig', 'app\api\controller\PaymentV2@usdtConfig');
    Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');  // 新路径

    // 项目列表（公开）
    Route::get('project/index', 'app\api\controller\Project@index');
    Route::get('project/detail', 'app\api\controller\Project@detail');

    // 找回密码
    Route::post('auth/forgotpassword/step1', 'app\api\controller\ForgotPassword@step1');
    Route::post('auth/forgotpassword/step2', 'app\api\controller\ForgotPassword@step2');
    Route::post('auth/forgotpassword/step3', 'app\api\controller\ForgotPassword@step3');
    Route::post('auth/forgotpassword/checkstatus', 'app\api\controller\ForgotPassword@checkStatus');

    // 支付回调（无需认证）
    Route::post('payment-v2/sevenpaynotify', 'app\api\controller\PaymentV2@sevenPayNotify');

    // Telegram Bot Webhook（无需认证）
    Route::post('telegram/webhook', 'app\api\controller\TelegramWebhook@index');

});


// ========== 需要登录的接口 ==========
Route::group('api', function () {

    // 支付V2（需要认证）- 放在前面确保优先匹配
    Route::post('payment-v2/wechat', 'app\api\controller\PaymentV2@wechat');
    Route::post('payment-v2/alipay', 'app\api\controller\PaymentV2@alipay');
    Route::post('payment-v2/usdtcreate', 'app\api\controller\PaymentV2@usdtCreate');
    Route::get('payment-v2/usdtcheck', 'app\api\controller\PaymentV2@usdtCheck');

    // 新支付路径（需要认证）
    Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');
    Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');
    Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate');
    Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');
    Route::post('pay/usdt-cancel', 'app\api\controller\PaymentV2@usdtCancel');

    // 银行卡充值
    Route::get('pay/bank-config', 'app\api\controller\PaymentV2@bankConfig');
    Route::post('pay/bank-create', 'app\api\controller\PaymentV2@bankCreate');
    Route::post('pay/bank-upload', 'app\api\controller\PaymentV2@bankUploadVoucher');
    Route::post('pay/bank-cancel', 'app\api\controller\PaymentV2@bankCancel');

    // 认证
    Route::post('auth/logout', 'app\api\controller\Auth@logout');

    // 用户
    Route::get('user/index', 'app\api\controller\User@index');
    Route::get('user/vip-progress', 'app\api\controller\User@vipProgress');
    Route::get('user/transaction/records', 'app\api\controller\User@transactionRecords');

    // 签到（执行签到，需要认证）
    Route::post('user/sign/sign', 'app\api\controller\Sign@sign');

}, ['middleware' => ['auth:api']]);
