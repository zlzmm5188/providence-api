-- ============================================
-- Providence 完整数据库迁移文件
-- 创建时间: 2025-11-23
-- 说明: 创建所有必需的表和字段
-- ============================================

-- 1. 创建积分商品表
CREATE TABLE IF NOT EXISTS `points_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '商品ID',
  `name` varchar(255) NOT NULL COMMENT '商品名称',
  `description` text COMMENT '商品描述',
  `points_price` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '积分价格',
  `image` varchar(500) DEFAULT NULL COMMENT '商品图片',
  `stock` int(11) DEFAULT 0 COMMENT '库存数量',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-上架，0-下架',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分商品表';

-- 2. 创建积分兑换订单表
CREATE TABLE IF NOT EXISTS `points_exchange_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '兑换数量',
  `points_cost` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '消耗积分',
  `status` varchar(20) DEFAULT 'pending' COMMENT '状态：pending-待处理，completed-已完成，cancelled-已取消',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_goods_id` (`goods_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分兑换订单表';

-- 3. 创建USDT汇率表
CREATE TABLE IF NOT EXISTS `usdt_rate` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `price` decimal(20,8) NOT NULL COMMENT '汇率（1 USDT = X CNY）',
  `source` varchar(50) DEFAULT 'manual' COMMENT '来源：manual-手动，api-API接口',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='USDT汇率表';

-- 4. 创建团队奖励表
CREATE TABLE IF NOT EXISTS `team_reward` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `level` int(11) NOT NULL COMMENT '奖励级别（3,5,10,20,50,100,200,500,1000）',
  `member_count` int(11) NOT NULL COMMENT '团队成员数',
  `total_invest` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '累计投资额',
  `points_reward` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '奖励积分',
  `status` varchar(20) DEFAULT 'pending' COMMENT '状态：pending-待发放，completed-已发放',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_level` (`level`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='团队奖励表';

-- 5. 创建日利宝表
CREATE TABLE IF NOT EXISTS `ribao` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `currency` varchar(10) NOT NULL DEFAULT 'CNY' COMMENT '币种：CNY/USDT',
  `balance` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '日利宝余额',
  `total_profit` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '累计收益',
  `yesterday_profit` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '昨日收益',
  `rate` decimal(10,6) NOT NULL DEFAULT '0.001500' COMMENT '日利率（0.15%）',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_currency` (`user_id`, `currency`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日利宝表';

-- 6. 添加users表字段（使用存储过程）
DELIMITER $$

DROP PROCEDURE IF EXISTS `add_users_fields`$$

CREATE PROCEDURE `add_users_fields`()
BEGIN
    -- team_reward_level
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'team_reward_level'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `team_reward_level` int(11) DEFAULT 0 COMMENT '团队奖励级别（0-9）';
    END IF;

    -- points
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'points'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `points` decimal(20,8) DEFAULT '0.00000000' COMMENT '积分余额';
    END IF;

    -- face_photo
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'face_photo'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `face_photo` text DEFAULT NULL COMMENT '人脸照片（base64或文件路径）';
    END IF;

    -- real_name
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'real_name'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名';
    END IF;

    -- id_card
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'id_card'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `id_card` varchar(18) DEFAULT NULL COMMENT '身份证号';
    END IF;

    -- balance_cny
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'balance_cny'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `balance_cny` decimal(20,8) DEFAULT '0.00000000' COMMENT 'CNY余额';
    END IF;

    -- balance_usdt
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'balance_usdt'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `balance_usdt` decimal(20,8) DEFAULT '0.00000000' COMMENT 'USDT余额';
    END IF;

    -- total_profit_cny
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'total_profit_cny'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `total_profit_cny` decimal(20,8) DEFAULT '0.00000000' COMMENT 'CNY累计收益';
    END IF;

    -- total_profit_usdt
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'total_profit_usdt'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `total_profit_usdt` decimal(20,8) DEFAULT '0.00000000' COMMENT 'USDT累计收益';
    END IF;

    -- vip_level
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'vip_level'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `vip_level` int(11) DEFAULT 0 COMMENT 'VIP等级（0-8）';
    END IF;

    -- ribao_cny
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'ribao_cny'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `ribao_cny` decimal(20,8) DEFAULT '0.00000000' COMMENT '日利宝CNY余额';
    END IF;

    -- ribao_usdt
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'ribao_usdt'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `ribao_usdt` decimal(20,8) DEFAULT '0.00000000' COMMENT '日利宝USDT余额';
    END IF;
END$$

DELIMITER ;

-- 执行存储过程
CALL `add_users_fields`();

-- 删除临时存储过程
DROP PROCEDURE IF EXISTS `add_users_fields`;

-- 7. 插入初始USDT汇率
INSERT INTO `usdt_rate` (`price`, `source`, `created_at`)
SELECT 7.2, 'manual', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `usdt_rate` LIMIT 1);
