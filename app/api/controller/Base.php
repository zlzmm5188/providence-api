<?php
namespace app\api\controller;

use think\facade\Request;

/**
 * API控制器基类
 */
class Base
{
    protected $userId = 0;

    public function __construct()
    {
        // 确保加载helpers
        if (!function_exists('get_user_id')) {
            require_once dirname(__DIR__, 2) . '/common/helpers.php';
        }

        // 从中间件获取用户ID（如果已通过认证）
        $this->userId = get_user_id();
    }
}
