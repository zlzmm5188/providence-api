<?php
/**
 * 应用路由配置文件
 *
 * 本文件是 ThinkPHP 应用的全局路由入口
 * 在此加载所有路由定义
 */

use think\facade\Route;

// 加载 API 路由文件
include_once __DIR__ . '/../route/api.php';

// 加载 Providence 路由文件
include_once __DIR__ . '/../route/providence.php';

// 默认路由 - 返回欢迎信息
Route::get('/', function () {
    return json([
        'message' => 'Providence API Server is running',
        'version' => '1.0.0',
        'time' => date('Y-m-d H:i:s'),
    ]);
});

// 健康检查端点
Route::get('/health', function () {
    return json([
        'status' => 'ok',
        'timestamp' => time(),
    ]);
});
