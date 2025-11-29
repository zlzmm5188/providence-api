<?php
use think\facade\Route;

/**
 * Providence 后台 API ---- 单层路由组版本（修复嵌套路由组问题）
 */

Route::group('api/providence', function () {

    // 登录模块（无需认证）
    Route::post('admin/login', 'app\providence\controller\Admin@login');
    Route::post('admin/logout', 'app\providence\controller\Admin@logout');
    Route::get('admin/me', 'app\providence\controller\Admin@getUserInfo');

    // 仪表盘（无需认证）
    Route::get('dashboard/statistics', 'app\providence\controller\Dashboard@statistics');

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

    // 系统配置
    Route::get('system/config', 'app\providence\controller\System@getConfig');
    Route::post('system/config', 'app\providence\controller\System@updateConfig');
    Route::get('system/vip-config', 'app\providence\controller\System@getVipConfig');
    Route::post('system/vip-config', 'app\providence\controller\System@updateVipConfig');
    Route::get('system/team-reward-config', 'app\providence\controller\System@getTeamRewardConfig');
    Route::post('system/team-reward-config', 'app\providence\controller\System@updateTeamRewardConfig');

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

});
