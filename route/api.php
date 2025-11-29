<?php
use think\facade\Route;

// ========== 无需登录的接口 ==========
Route::group('api', function () {

    // 认证
    Route::post('auth/register', 'app\api\controller\Auth@register');
    Route::post('auth/login', 'app\api\controller\Auth@login');  // 管理员登录（后台专用）
    Route::post('user/login', 'app\api\controller\User@login');  // 用户登录（前台专用）
    Route::get('auth/codes', 'app\api\controller\Auth@codes');  // 权限代码

    // 管理员相关
    Route::get('admin/info', 'app\api\controller\Admin@info');
    Route::get('admin/list', 'app\api\controller\Admin@list');

    // Providence后台管理API
    Route::group('providence', function () {
        // 仪表盘
        Route::get('dashboard/stats', 'app\api\controller\Providence@dashboardStats');

        // 用户管理
        Route::get('users', 'app\api\controller\Providence@userList');
        Route::post('users/freeze', 'app\api\controller\Providence@freezeUser');
        Route::post('users/unfreeze', 'app\api\controller\Providence@unfreezeUser');

        // 充值管理
        Route::get('recharge', 'app\api\controller\Providence@rechargeList');
        Route::post('recharge/approve', 'app\api\controller\Providence@approveRecharge');
        Route::post('recharge/reject', 'app\api\controller\Providence@rejectRecharge');

        // 提现管理
        Route::get('withdraw', 'app\api\controller\Providence@withdrawList');
        Route::post('withdraw/approve', 'app\api\controller\Providence@approveWithdraw');
        Route::post('withdraw/reject', 'app\api\controller\Providence@rejectWithdraw');

        // 项目管理
        Route::get('projects', 'app\api\controller\Providence@projectList');
        Route::post('projects/create', 'app\api\controller\Providence@createProject');
        Route::post('projects/update', 'app\api\controller\Providence@updateProject');
        Route::post('projects/delete', 'app\api\controller\Providence@deleteProject');

        // 系统配置
        Route::get('config', 'app\api\controller\Providence@getConfig');
        Route::post('config/update', 'app\api\controller\Providence@updateConfig');

        // 统计分析
        Route::get('analytics/stats', 'app\api\controller\Providence@getAnalyticsStats');

        // KYC管理
        Route::get('kyc/stats', 'app\api\controller\Providence@getKycStats');
        Route::get('kyc/list', 'app\api\controller\Providence@getKycList');
        Route::get('kyc/detail', 'app\api\controller\Providence@getKycDetail');
        Route::post('kyc/approve', 'app\api\controller\Providence@approveKyc');
        Route::post('kyc/reject', 'app\api\controller\Providence@rejectKyc');

        // 操作日志
        Route::get('logs', 'app\api\controller\Providence@getLogs');
    });

    // 安装（创建表，仅本地调用）
    Route::get('install/create-sign-tables', 'app\api\controller\Install@createSignTables');

    // 用户信息（自动识别用户/管理员）
    Route::get('user/info', 'app\api\controller\User@info');

    // 签到（获取信息，未登录也可查看）
    Route::get('user/sign/info', 'app\api\controller\Sign@info');

    // 支付配置（公开，无需认证）- 放在前面确保优先匹配
    Route::get('payment-v2/usdtconfig', 'app\api\controller\PaymentV2@usdtConfig');
    Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');  // 新路径（带 /api/ 前缀）

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

    // 新支付路径（需要认证）- 更简洁的路径（带 /api/ 前缀）
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
    Route::get('user/vip-progress', 'app\api\controller\User@vipProgress');  // VIP进度（带连字符）
    Route::get('user/vipprogress', 'app\api\controller\User@vipProgress');  // 兼容旧路径
    Route::get('user/transaction/records', 'app\api\controller\User@transactionRecords');

    // 签到（执行签到，需要认证）
    Route::post('user/sign/sign', 'app\api\controller\Sign@sign');

    // 日利宝
    Route::get('user/ribao/head', 'app\api\controller\Ribao@head');
    Route::get('user/ribao/info', 'app\api\controller\Ribao@info');
    Route::get('user/ribao/records', 'app\api\controller\Ribao@records');
    Route::post('user/ribao/transferin', 'app\api\controller\Ribao@transferIn');
    Route::post('user/ribao/transferout', 'app\api\controller\Ribao@transferOut');

    // 投资
    Route::post('invest/create', 'app\api\controller\Invest@create');
    Route::get('invest/orders', 'app\api\controller\Invest@orders');
    Route::post('invest/settle', 'app\api\controller\Invest@settle'); // 订单结算

    // 充值
    Route::post('recharge/add', 'app\api\controller\Recharge@add');
    Route::get('recharge/list', 'app\api\controller\Recharge@list');

    // 提现
    Route::post('withdraw/create', 'app\api\controller\Withdraw@create');
    Route::get('withdraw/list', 'app\api\controller\Withdraw@list');

    // 团队
    Route::get('team/members', 'app\api\controller\Team@members');
    Route::get('team/rewardinfo', 'app\api\controller\Team@rewardInfo');  // 旧路径（兼容）
    Route::get('team/reward-info', 'app\api\controller\Team@rewardInfo');  // 新路径（带连字符）
    Route::post('team/claimreward', 'app\api\controller\Team@claimReward');

    // 积分
    Route::get('points/balance', 'app\api\controller\Points@balance');
    Route::get('points/logs', 'app\api\controller\Points@logs');
    Route::post('points/exchange', 'app\api\controller\Points@exchange');

    // 币种兑换
    Route::get('currency/get-usdt-rate', 'app\api\controller\Currency@getRate');
    Route::post('currency/exchange-cny-to-usdt', 'app\api\controller\Currency@exchangeCnyToUsdt');

    // AI客服
    Route::post('ai/chat', 'app\api\controller\AIChat@chat');

    // KYC实名认证
    Route::post('user/kyc/submit', 'app\api\controller\Kyc@submit');
    Route::get('user/kyc/status', 'app\api\controller\Kyc@status');

    // 人脸识别
    Route::post('user/face-upload', 'app\api\controller\Face@upload');
    Route::post('user/face-verify', 'app\api\controller\Face@verify');
    Route::get('user/face/status', 'app\api\controller\Face@status');

    // 银行卡管理
    Route::get('user/bank/list', 'app\api\controller\BankCard@list');
    Route::post('user/bank/add', 'app\api\controller\BankCard@add');
    Route::post('user/bank/del', 'app\api\controller\BankCard@del');

})->middleware(\app\common\middleware\AuthMiddleware::class);

// ========== Providence后台路由：无需认证部分 ==========
Route::group('api/providence', function () {
    // 管理员登录（无需认证）
    Route::post('admin/login', 'app\providence\controller\Admin@login');
    Route::post('admin/logout', 'app\providence\controller\Admin@logout');
    Route::get('admin/me', 'app\providence\controller\Admin@getUserInfo');

    // 仪表盘（无需认证）
    Route::get('dashboard/statistics', 'app\providence\controller\Dashboard@statistics');

    // 系统配置（无需认证）
    Route::get('system/config', 'app\providence\controller\System@getConfig');
    Route::get('system/vip-config', 'app\providence\controller\System@getVipConfig');
    Route::get('system/team-reward-config', 'app\providence\controller\System@getTeamRewardConfig');

    // 项目管理（只读，无需认证）
    Route::get('projects', 'app\providence\controller\Project@index');
    Route::get('projects/:id', 'app\providence\controller\Project@detail');
});

// ========== Providence后台路由：需要认证部分 ==========
Route::group('api/providence', function () {
    // 用户管理
    Route::get('users', 'app\providence\controller\User@index');
    Route::get('users/:id', 'app\providence\controller\User@detail');
    Route::post('users/:id/vip', 'app\providence\controller\User@updateVip');
    Route::post('users/:id/balance', 'app\providence\controller\User@updateBalance');
    Route::post('users/:id/status', 'app\providence\controller\User@toggleStatus');
    Route::post('users/:id/internal', 'app\providence\controller\User@toggleInternal');
    Route::post('users/:id/reset-password', 'app\providence\controller\User@resetPassword');
    Route::post('users/:id/kyc/approve', 'app\providence\controller\User@approveKyc');
    Route::post('users/:id/kyc/reject', 'app\providence\controller\User@rejectKyc');

    // 充值管理
    Route::get('recharge', 'app\providence\controller\Recharge@index');
    Route::post('recharge/approve', 'app\providence\controller\Recharge@approve');
    Route::post('recharge/reject', 'app\providence\controller\Recharge@reject');

    // 提现管理
    Route::get('withdraw', 'app\providence\controller\Withdraw@index');
    Route::post('withdraw/approve', 'app\providence\controller\Withdraw@approve');
    Route::post('withdraw/reject', 'app\providence\controller\Withdraw@reject');

    // KYC管理
    Route::get('kyc', 'app\providence\controller\Kyc@index');
    Route::get('kyc/:id', 'app\providence\controller\Kyc@detail');

    // 系统配置（修改需要认证）
    Route::post('system/config', 'app\providence\controller\System@updateConfig');
    Route::post('system/vip-config', 'app\providence\controller\System@updateVipConfig');
    Route::post('system/team-reward-config', 'app\providence\controller\System@updateTeamRewardConfig');

    // 项目管理（修改和创建需要认证）
    Route::post('projects', 'app\providence\controller\Project@create');
    Route::put('projects/:id', 'app\providence\controller\Project@update');
    Route::delete('projects/:id', 'app\providence\controller\Project@delete');
    Route::post('projects/batch-status', 'app\providence\controller\Project@batchStatus');

    // 项目板块
    Route::get('project-categories', 'app\providence\controller\ProjectCategory@index');
    Route::post('project-categories', 'app\providence\controller\ProjectCategory@create');
    Route::delete('project-categories/:id', 'app\providence\controller\ProjectCategory@delete');

    // 日利宝管理
    Route::get('ribao/records', 'app\providence\controller\Ribao@records');
    Route::get('ribao/config', 'app\providence\controller\Ribao@getConfig');
    Route::post('ribao/config', 'app\providence\controller\Ribao@updateConfig');

    // 体验金管理
    Route::get('trial-funds', 'app\providence\controller\TrialFund@index');
    Route::post('trial-funds/:id/recover', 'app\providence\controller\TrialFund@recover');
    Route::get('trial-funds/statistics', 'app\providence\controller\TrialFund@statistics');
    Route::get('trial-funds/config', 'app\providence\controller\TrialFund@getConfig');
    Route::post('trial-funds/config', 'app\providence\controller\TrialFund@updateConfig');

    // 团队管理（后台）
    Route::get('team/rewards', 'app\providence\controller\Team@rewards');
    Route::get('team/rules', 'app\providence\controller\Team@getRules');
    Route::post('team/rules', 'app\providence\controller\Team@updateRules');

    // 内容管理
    Route::get('announcements', 'app\providence\controller\Content@announcements');
    Route::get('popups', 'app\providence\controller\Content@popups');
    Route::post('popups', 'app\providence\controller\Content@createPopup');
    Route::put('popups/:id', 'app\providence\controller\Content@updatePopup');
    Route::delete('popups/:id', 'app\providence\controller\Content@deletePopup');
    Route::get('activities', 'app\providence\controller\Content@activities');

    // 系统监控
    Route::get('audit-logs', 'app\providence\controller\Monitor@auditLogs');
    Route::get('login-logs', 'app\providence\controller\Monitor@loginLogs');
    Route::get('risk/alerts', 'app\providence\controller\Monitor@riskAlerts');
    Route::get('risk/blacklist', 'app\providence\controller\Monitor@riskBlacklist');
    Route::post('risk/blacklist', 'app\providence\controller\Monitor@addBlacklist');
    Route::get('risk/rules', 'app\providence\controller\Monitor@riskRules');
    Route::post('risk/rules', 'app\providence\controller\Monitor@updateRiskRules');

    // 人脸识别
    Route::get('face-verification', 'app\providence\controller\FaceVerification@index');
    Route::post('face-verification/:id/approve', 'app\providence\controller\FaceVerification@approve');
    Route::post('face-verification/:id/reject', 'app\providence\controller\FaceVerification@reject');
    Route::get('face-verification/statistics', 'app\providence\controller\FaceVerification@statistics');
    Route::get('face-verification/settings', 'app\providence\controller\FaceVerification@getSettings');
    Route::post('face-verification/settings', 'app\providence\controller\FaceVerification@updateSettings');

    // 钱包流水
    Route::get('wallet-logs', 'app\providence\controller\WalletLog@index');

    // 订单管理
    Route::get('orders', 'app\providence\controller\Order@index');
    Route::get('orders/:id', 'app\providence\controller\Order@detail');

    // 收益记录
    Route::get('earnings', 'app\providence\controller\Earning@index');

})->middleware(\app\common\middleware\AuthMiddleware::class);

// Bot专用API
Route::post("bot/admin/project/publish", "app\\api\\controller\\BotAdmin@publishProject");
Route::get("bot/admin/finance/stats", "app\\api\\controller\\BotAdmin@getFinanceStats");

// Fund API（兼容旧版前端路径） - 不带api前缀
Route::get("fund/project/all", "app\\api\\controller\\Project@index");

// Fund API（兼容旧版前端路径） - 带api前缀
Route::group('api', function() {
    Route::get("fund/project/all", "app\\api\\controller\\Project@index");
});

// ========== 支付接口（不带 /api/ 前缀） ==========
// 无需登录的支付接口
Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');
Route::get('pay/bank-config', 'app\api\controller\PaymentV2@bankConfig');
// USDT监控接口（需要登录，用于手动触发监控）
Route::get('pay/usdt-monitor', 'app\api\controller\PaymentV2@usdtMonitor');

// 需要登录的支付接口
Route::group('', function () {
    Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');
    Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');
    Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate');
    Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');
    Route::post('pay/usdt-cancel', 'app\api\controller\PaymentV2@usdtCancel');
    Route::get('pay/usdt-monitor', 'app\api\controller\PaymentV2@usdtMonitor');  // 手动触发监控
    // 银行卡充值
    Route::post('pay/bank-create', 'app\api\controller\PaymentV2@bankCreate');
    Route::post('pay/bank-upload', 'app\api\controller\PaymentV2@bankUploadVoucher');
    Route::post('pay/bank-cancel', 'app\api\controller\PaymentV2@bankCancel');
})->middleware(\app\common\middleware\AuthMiddleware::class);

// ========== Providence后台路由已合并到上面的路由组中 ==========
// require_once __DIR__ . '/providence.php'; // 已注释，路由已合并到api.php中
