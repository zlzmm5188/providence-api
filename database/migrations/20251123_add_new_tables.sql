-- ============================================
-- Providence 数据库迁移文件
-- 创建时间: 2025-11-23
-- 说明: 添加积分兑换、币种兑换、用户字段等表结构
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

-- 4. 检查并添加users表字段
-- 检查team_reward_level字段
SET @exist_team_reward_level := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'team_reward_level'
);

SET @sql_team_reward_level := IF(@exist_team_reward_level = 0,
    'ALTER TABLE `users` ADD COLUMN `team_reward_level` int(11) DEFAULT 0 COMMENT ''团队奖励级别（0-9）'' AFTER `vip_level`;',
    'SELECT ''字段 team_reward_level 已存在'' AS message;'
);

PREPARE stmt FROM @sql_team_reward_level;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查real_name字段
SET @exist_real_name := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'real_name'
);

SET @sql_real_name := IF(@exist_real_name = 0,
    'ALTER TABLE `users` ADD COLUMN `real_name` varchar(50) DEFAULT NULL COMMENT ''真实姓名'' AFTER `username`;',
    'SELECT ''字段 real_name 已存在'' AS message;'
);

PREPARE stmt FROM @sql_real_name;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查id_card字段
SET @exist_id_card := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'id_card'
);

SET @sql_id_card := IF(@exist_id_card = 0,
    'ALTER TABLE `users` ADD COLUMN `id_card` varchar(18) DEFAULT NULL COMMENT ''身份证号'' AFTER `real_name`;',
    'SELECT ''字段 id_card 已存在'' AS message;'
);

PREPARE stmt FROM @sql_id_card;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查face_photo字段
SET @exist_face_photo := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'face_photo'
);

SET @sql_face_photo := IF(@exist_face_photo = 0,
    'ALTER TABLE `users` ADD COLUMN `face_photo` text DEFAULT NULL COMMENT ''人脸照片（base64或文件路径）'' AFTER `id_card`;',
    'SELECT ''字段 face_photo 已存在'' AS message;'
);

PREPARE stmt FROM @sql_face_photo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. 插入初始USDT汇率（如果表为空）
INSERT INTO `usdt_rate` (`price`, `source`, `created_at`)
SELECT 7.2, 'manual', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `usdt_rate` LIMIT 1);
