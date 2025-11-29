<?php
/**
 * API 应用路由定义
 * 使用完整命名空间解决多应用路由冲突
 */
use think\facade\Route;

$ns = 'app\api\controller\\';

// 健康检查（使用Index控制器的health方法）
Route::get('health', 'app\controller\Index@health');

// ========== Providence后台路由（必须在api应用路由中定义） ==========
// 注意：在api应用的路由文件中，URL /api/providence/... 会被解析为：
// - 应用：api
// - 剩余路径：providence/...
// 所以路由定义应该直接匹配 providence/... 而不需要加 api 前缀
Route::group('providence', function () {
    // 管理员登录（无需认证）
    Route::post('admin/login', 'app\providence\controller\Admin@login');
    Route::post('admin/logout', 'app\providence\controller\Admin@logout');
    Route::get('admin/me', 'app\providence\controller\Admin@getUserInfo');
    Route::get('admin/profile', 'app\providence\controller\Admin@getUserInfo');  // 前端期望的路径

    // Dashboard 统计数据
    Route::get('dashboard/statistics', 'app\providence\controller\AdminDashboard@statistics');
    Route::get('dashboard/overview', 'app\providence\controller\AdminDashboard@overview');

    // 用户、充值、提现数据（用于后台展示）
    Route::get('user/list', 'app\providence\controller\AdminDashboard@userList');
    Route::get('recharge/list', 'app\providence\controller\AdminDashboard@rechargeList');
    Route::get('withdraw/list', 'app\providence\controller\AdminDashboard@withdrawList');

    // 体验金管理（需要认证）
    Route::get('trial-funds', 'app\providence\controller\TrialFund@index');
    Route::post('trial-funds/:id/recover', 'app\providence\controller\TrialFund@recover');
    Route::get('trial-funds/statistics', 'app\providence\controller\TrialFund@statistics');
    Route::get('trial-funds/config', 'app\providence\controller\TrialFund@getConfig');
    Route::post('trial-funds/config', 'app\providence\controller\TrialFund@updateConfig');

    // ========== 需要管理员认证的路由 ==========
    // 注意：这些路由需要认证中间件，但ThinkPHP多应用模式下
    // 需要在控制器中或通过中间件组来处理认证

    // 注意：dashboard/statistics 已在上面定义，使用 AdminDashboard@statistics

    // 用户管理
    Route::get('users', 'app\providence\controller\User@index');
    Route::get('users/:id', 'app\providence\controller\User@detail');
    Route::post('users/:id/vip', 'app\providence\controller\User@updateVip');
    Route::post('users/:id/balance', 'app\providence\controller\User@updateBalance');
    Route::post('users/:id/status', 'app\providence\controller\User@toggleStatus');
    Route::post('users/:id/internal', 'app\providence\controller\User@toggleInternal');
    Route::get('users/internal', 'app\providence\controller\User@internalUsers');
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
    Route::post('withdraw/processing', 'app\providence\controller\Withdraw@processing');
    Route::post('withdraw/completed', 'app\providence\controller\Withdraw@completed');

    // 钱包流水
    Route::get('wallet-logs', 'app\providence\controller\WalletLog@index');

    // 订单管理
    Route::get('orders', 'app\providence\controller\Order@index');
    Route::get('orders/:id', 'app\providence\controller\Order@detail');

    // 收益记录
    Route::get('earnings', 'app\providence\controller\Earning@index');

    // KYC管理
    Route::get('kyc', 'app\providence\controller\Kyc@index');

    // 管理员管理
    Route::group('admins', function () {
        Route::get('/', 'app\providence\controller\AdminList@index');
        Route::post('/', 'app\providence\controller\AdminList@create');
        Route::put('/:id', 'app\providence\controller\AdminList@update');
        Route::delete('/:id', 'app\providence\controller\AdminList@delete');
        Route::post('/:id/reset-password', 'app\providence\controller\AdminList@resetPassword');
    });

    // 项目管理
    Route::get('projects', 'app\providence\controller\Project@index');
    Route::get('projects/:id', 'app\providence\controller\Project@detail');
    Route::post('projects', 'app\providence\controller\Project@create');
    Route::put('projects/:id', 'app\providence\controller\Project@update');
    Route::delete('projects/:id', 'app\providence\controller\Project@delete');
    Route::post('projects/batch-status', 'app\providence\controller\Project@batchStatus');

    // 项目板块
    Route::get('project-categories', 'app\providence\controller\ProjectCategory@index');
    Route::post('project-categories', 'app\providence\controller\ProjectCategory@create');
    Route::delete('project-categories/:id', 'app\providence\controller\ProjectCategory@delete');

    // 日利宝管理（文档中未明确列出，但前端需要，保留）
    Route::get('ribao/records', 'app\providence\controller\Ribao@records');
    Route::get('ribao/stats', 'app\providence\controller\Ribao@stats');
    Route::get('ribao/users', 'app\providence\controller\Ribao@users');
    Route::get('ribao/config', 'app\providence\controller\Ribao@getConfig');
    Route::post('ribao/config', 'app\providence\controller\Ribao@updateConfig');
    Route::post('ribao/trigger-earnings', 'app\providence\controller\Ribao@triggerEarnings');

    // 返利管理（文档中未明确列出，但前端需要，保留）
    Route::get('rebate/stats', 'app\providence\controller\Rebate@stats');
    Route::get('rebate/list', 'app\providence\controller\Rebate@list');

    // 系统配置
    Route::get('system/config', 'app\providence\controller\System@getConfig');
    Route::post('system/config', 'app\providence\controller\System@updateConfig');
    Route::post('system/config/batch', 'app\providence\controller\System@batchUpdateConfig');
    Route::get('system/vip-config', 'app\providence\controller\System@getVipConfig');
    Route::post('system/vip-config', 'app\providence\controller\System@updateVipConfig');
    Route::get('system/team-reward-config', 'app\providence\controller\System@getTeamRewardConfig');
    Route::post('system/team-reward-config', 'app\providence\controller\System@updateTeamRewardConfig');
    Route::get('system/team-config', 'app\providence\controller\System@getTeamConfig');
    Route::post('system/team-config', 'app\providence\controller\System@updateTeamConfig');

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
    Route::post('activities', 'app\providence\controller\Content@createActivity');
    Route::put('activities/:id', 'app\providence\controller\Content@updateActivity');
    Route::delete('activities/:id', 'app\providence\controller\Content@deleteActivity');
    Route::post('activities/:id/status', 'app\providence\controller\Content@toggleActivityStatus');
    Route::get('activities/:id/statistics', 'app\providence\controller\Content@getActivityStatistics');

    // 系统监控
    Route::get('audit-logs', 'app\providence\controller\Monitor@auditLogs');
    Route::get('login-logs', 'app\providence\controller\Monitor@loginLogs');
    Route::get('risk/stats', 'app\providence\controller\Monitor@riskStats');
    Route::get('risk/alerts', 'app\providence\controller\Monitor@riskAlerts');
    Route::post('risk/alerts/:id/handle', 'app\providence\controller\Monitor@handleAlert');
    Route::post('risk/alerts/:id/resolve', 'app\providence\controller\Monitor@resolveAlert');
    Route::post('risk/alerts/:id/ignore', 'app\providence\controller\Monitor@ignoreAlert');
    Route::get('risk/blacklist', 'app\providence\controller\Monitor@riskBlacklist');
    Route::post('risk/blacklist', 'app\providence\controller\Monitor@addBlacklist');
    Route::post('risk/blacklist/remove', 'app\providence\controller\Monitor@removeBlacklist');
    Route::get('risk/rules', 'app\providence\controller\Monitor@riskRules');
    Route::post('risk/rules', 'app\providence\controller\Monitor@updateRiskRules');

    // 人脸识别
    Route::get('face-verification', 'app\providence\controller\FaceVerification@index');
    Route::post('face-verification/:id/approve', 'app\providence\controller\FaceVerification@approve');
    Route::post('face-verification/:id/reject', 'app\providence\controller\FaceVerification@reject');
    Route::get('face-verification/statistics', 'app\providence\controller\FaceVerification@statistics');
    Route::get('face-verification/settings', 'app\providence\controller\FaceVerification@getSettings');
    Route::post('face-verification/settings', 'app\providence\controller\FaceVerification@updateSettings');

});

// 签到接口
Route::get('user/sign/info', $ns . 'Sign@info');
Route::post('user/sign/sign', $ns . 'Sign@sign');

// 用户信息
Route::get('user/info', $ns . 'User@info');
Route::get('user/index', $ns . 'User@index');
Route::get('user/vip-progress', $ns . 'User@vipProgress');
Route::get('user/vipprogress', $ns . 'User@vipProgress');
Route::get('user/transaction/records', $ns . 'User@transactionRecords');
Route::post('user/change-password', $ns . 'User@changePassword');
Route::post('user/bind-bank-card', $ns . 'User@bindBankCard');
Route::post('user/bind-usdt-address', $ns . 'User@bindUsdtAddress');

// 日利宝
Route::get('user/ribao/head', $ns . 'Ribao@head');
Route::get('user/ribao/info', $ns . 'Ribao@info');
Route::get('user/ribao/records', $ns . 'Ribao@records');
Route::post('user/ribao/transferin', $ns . 'Ribao@transferIn');
Route::post('user/ribao/transferout', $ns . 'Ribao@transferOut');

// 认证
Route::post('auth/register', $ns . 'Auth@register');
Route::post('auth/login', $ns . 'Auth@login');
Route::post('user/login', $ns . 'User@login');
Route::get('auth/codes', $ns . 'Auth@codes');

// 项目
Route::get('project/index', $ns . 'Project@index');
Route::get('project/detail', $ns . 'Project@detail');
// 项目路由兼容（新格式）
Route::get('projects', $ns . 'Project@index');
Route::get('projects/categories', $ns . 'Project@categories');
Route::get('projects/:id', $ns . 'Project@detail');
Route::post('projects/:projectId/invest', $ns . 'Invest@create');

// 支付（按文档路径）
Route::get('pay/usdt-config', $ns . 'PaymentV2@usdtConfig');
Route::post('pay/usdt-create', $ns . 'PaymentV2@usdtCreate');
Route::get('pay/usdt-check', $ns . 'PaymentV2@usdtCheck');
Route::post('pay/usdt-cancel', $ns . 'PaymentV2@usdtCancel');
Route::post('pay/wechat-create', $ns . 'PaymentV2@wechat');
Route::post('pay/alipay-create', $ns . 'PaymentV2@alipay');
Route::get('pay/bank-config', $ns . 'PaymentV2@bankConfig');
Route::post('pay/bank-create', $ns . 'PaymentV2@bankCreate');
Route::post('pay/bank-upload', $ns . 'PaymentV2@bankUploadVoucher');
Route::post('payment-v2/sevenpaynotify', $ns . 'PaymentV2@sevenPayNotify'); // 回调保持原路径

// 投资（按文档路径）
Route::post('invest/create', $ns . 'Invest@create');
Route::get('invest/orders', $ns . 'Invest@orders');
// 订单路由兼容（新格式）
Route::get('orders/my-investments', $ns . 'Invest@orders');
Route::get('orders/earnings', $ns . 'Earning@list');

// 充值提现（按文档路径）
Route::post('recharge/add', $ns . 'Recharge@add');
Route::get('recharge/list', $ns . 'Recharge@list');
Route::get('withdraw/list', $ns . 'Withdraw@list');
Route::post('withdraw/create', $ns . 'Withdraw@create');
// 财务路由兼容（新格式）
Route::post('finance/recharge', $ns . 'Recharge@add');
Route::post('finance/withdraw', $ns . 'Withdraw@create');
Route::get('finance/recharge/list', $ns . 'Recharge@list');
Route::get('finance/withdraw/list', $ns . 'Withdraw@list');
Route::get('finance/wallet-logs', $ns . 'WalletLog@list');

// 银行卡
Route::get('pay/bank/list', $ns . 'BankCard@list');
Route::post('pay/bank/add', $ns . 'BankCard@add');
Route::post('pay/bank/del', $ns . 'BankCard@del');

// 团队（按文档路径）
Route::get('team/members', $ns . 'Team@members');
Route::get('team/reward-info', $ns . 'Team@rewardInfo');
Route::post('team/claimreward', $ns . 'Team@claimReward');
Route::get('team/info', $ns . 'Team@info');
Route::get('team/referral-rewards', $ns . 'Team@referralRewards');

// KYC
Route::post('user/kyc/submit', $ns . 'Kyc@submit');
Route::get('user/kyc/status', $ns . 'Kyc@status');

// 人脸
Route::post('user/face-upload', $ns . 'Face@upload');
Route::post('user/face-verify', $ns . 'Face@verify');
Route::get('user/face/status', $ns . 'Face@status');

// 银行卡管理
Route::get('user/bank/list', $ns . 'BankCard@list');
Route::post('user/bank/add', $ns . 'BankCard@add');
Route::post('user/bank/del', $ns . 'BankCard@del');

// 找回密码
Route::post('auth/forgotpassword/step1', $ns . 'ForgotPassword@step1');
Route::post('auth/forgotpassword/step2', $ns . 'ForgotPassword@step2');
Route::post('auth/forgotpassword/step3', $ns . 'ForgotPassword@step3');
Route::post('auth/forgotpassword/checkstatus', $ns . 'ForgotPassword@checkStatus');
// 密码管理（兼容前端调用）
Route::post('user/forgot-password', $ns . 'ForgotPassword@step1');
Route::post('user/reset-password', $ns . 'ForgotPassword@step3');

// 积分（按文档路径）
Route::get('points/balance', $ns . 'Points@balance');
Route::get('points/logs', $ns . 'Points@logs');
Route::post('points/exchange', $ns . 'Points@exchange');
// 积分路由兼容（新格式）
Route::get('user/points/balance', $ns . 'Points@balance');
Route::get('user/points/logs', $ns . 'Points@logs');
Route::post('user/points/exchange', $ns . 'Points@exchange');

// 体验金（用户端）
Route::post('trial-fund/claim', $ns . 'TrialFund@claim');
Route::get('trial-fund/status', $ns . 'TrialFund@status');
Route::get('trial-fund/orders', $ns . 'TrialFund@orders');

// 内容管理（用户端）
Route::get('announcements', $ns . 'Content@announcements');
Route::get('activities/popup', $ns . 'Content@popup');
Route::post('activities/:id/participate', $ns . 'Content@participate');

// Telegram Webhook
Route::post('telegram/webhook', $ns . 'TelegramWebhook@index');

// 安装
Route::get('install/create-sign-tables', $ns . 'Install@createSignTables');
