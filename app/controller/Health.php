<?php
namespace app\controller;

use think\facade\Db;

/**
 * 健康检查控制器
 */
class Health
{
    /**
     * 健康检查端点
     */
    public function index()
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'checks' => []
        ];

        // 检查数据库连接
        try {
            Db::query('SELECT 1');
            $checks['checks']['database'] = [
                'status' => 'ok',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $checks['status'] = 'degraded';
            $checks['checks']['database'] = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // 检查 PHP 版本
        $checks['checks']['php'] = [
            'status' => 'ok',
            'version' => PHP_VERSION
        ];

        // 检查磁盘空间
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsedPercent = round((1 - $diskFree / $diskTotal) * 100, 2);

        $checks['checks']['disk'] = [
            'status' => $diskUsedPercent < 90 ? 'ok' : 'warning',
            'used_percent' => $diskUsedPercent . '%',
            'free' => $this->formatBytes($diskFree),
            'total' => $this->formatBytes($diskTotal)
        ];

        // 系统负载
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $checks['checks']['system_load'] = [
                'status' => 'ok',
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        }

        return json($checks);
    }

    /**
     * 格式化字节
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
