<?php
namespace app\api\controller;

use think\facade\Db;

/**
 * 数据库初始化控制器
 * 用于创建签到表（开发调试用）
 * 生产环境应通过迁移文件执行
 */
class Install
{
    /**
     * 创建签到相关表
     * GET /api/install/create-sign-tables
     */
    public function createSignTables()
    {
        try {
            // 仅允许开发环境调用
            if (!in_array(request()->ip(), ['127.0.0.1', '::1', 'localhost'])) {
                return json([
                    'code' => -1,
                    'msg' => '仅允许本地调用',
                    'data' => []
                ]);
            }

            // 1. 创建签到日志表
            $createTableSQL = <<<SQL
CREATE TABLE IF NOT EXISTS `sign_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '签到记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `sign_date` date NOT NULL COMMENT '签到日期',
  `points_reward` int(11) NOT NULL DEFAULT 0 COMMENT '获得积分',
  `continuous_days` int(11) NOT NULL DEFAULT 0 COMMENT '连续签到天数',
  `bonus_flag` tinyint(1) DEFAULT 0 COMMENT '是否获得7天奖励（1-是，0-否）',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_sign_date` (`user_id`, `sign_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_sign_date` (`sign_date`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户签到日志表'
SQL;

            Db::execute($createTableSQL);

            // 2. 添加users表字段
            $fields = [
                'last_checkin_date' => 'date DEFAULT NULL COMMENT "最后一次签到日期"',
                'total_checkins' => 'int(11) DEFAULT 0 COMMENT "累计签到次数"',
                'continuous_checkins' => 'int(11) DEFAULT 0 COMMENT "连续签到天数"'
            ];

            foreach ($fields as $field => $definition) {
                try {
                    $checkColumn = Db::query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='{$field}'");
                    if (empty($checkColumn)) {
                        Db::execute("ALTER TABLE `users` ADD COLUMN `{$field}` {$definition}");
                    }
                } catch (\Exception $e) {
                    // 忽略字段已存在的错误
                }
            }

            return json([
                'code' => 0,
                'msg' => '签到表创建成功',
                'data' => [
                    'sign_logs_table' => '已创建',
                    'users_fields' => '已添加'
                ]
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => -1,
                'msg' => '创建失败: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }
}
