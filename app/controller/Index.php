<?php
namespace app\controller;

/**
 * API首页控制器
 */
class Index
{
    /**
     * 首页 - API服务状态
     */
    public function index()
    {
        return json([
            'code' => 1,
            'msg' => 'Providence API服务正常运行',
            'data' => [
                'server' => 'Providence Investment Platform API',
                'version' => '1.0.0',
                'framework' => 'ThinkPHP 6.0',
                'php_version' => PHP_VERSION,
                'time' => date('Y-m-d H:i:s'),
                'endpoints' => [
                    'api_base' => '/api',
                    'docs' => '/api/docs',
                    'health' => '/api/health'
                ],
                'status' => 'running'
            ]
        ]);
    }

    /**
     * 健康检查
     */
    public function health()
    {
        return json([
            'status' => 'healthy',
            'timestamp' => time(),
            'uptime' => sys_getloadavg()[0]
        ]);
    }
}
