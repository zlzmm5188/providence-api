-- ==========================================
-- 签到日志表
-- 创建时间: 2025-11-24
-- ==========================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户签到日志表';

-- ==========================================
-- 向users表添加签到相关字段（如果不存在）
-- ==========================================

DELIMITER $$

DROP PROCEDURE IF EXISTS `add_sign_fields_to_users`$$

CREATE PROCEDURE `add_sign_fields_to_users`()
BEGIN
    -- last_checkin_date: 最后一次签到日期
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'last_checkin_date'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `last_checkin_date` date DEFAULT NULL COMMENT '最后一次签到日期';
    END IF;

    -- total_checkins: 累计签到次数
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'total_checkins'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `total_checkins` int(11) DEFAULT 0 COMMENT '累计签到次数';
    END IF;

    -- continuous_checkins: 连续签到天数
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'continuous_checkins'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `continuous_checkins` int(11) DEFAULT 0 COMMENT '连续签到天数';
    END IF;
END$$

DELIMITER ;

CALL `add_sign_fields_to_users`();
DROP PROCEDURE `add_sign_fields_to_users`;
