<?php
use think\facade\Route;

// Providence后台路由（在api应用下）
Route::group('api', function () {
    Route::group('providence', function () {
        // 管理员登录（无需认证）
        Route::post('admin/login', 'app\providence\controller\Admin@login');
        Route::post('admin/logout', 'app\providence\controller\Admin@logout');
        Route::get('admin/me', 'app\providence\controller\Admin@getUserInfo');
    });
});
