-- ==========================================
-- 用户银行卡表
-- 创建时间: 2025-11-24
-- ==========================================

CREATE TABLE IF NOT EXISTS `user_bank_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '银行卡ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `card_number` varchar(20) NOT NULL COMMENT '银行卡号',
  `bank_name` varchar(50) NOT NULL COMMENT '银行名称',
  `account_name` varchar(50) NOT NULL COMMENT '开户人姓名',
  `account_branch` varchar(100) DEFAULT NULL COMMENT '开户支行',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，0-删除',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_card` (`user_id`, `card_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户银行卡表';

-- ==========================================
-- 用户USDT地址表
-- ==========================================

CREATE TABLE IF NOT EXISTS `user_usdt_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '地址ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `address` varchar(100) NOT NULL COMMENT 'USDT钱包地址',
  `network` varchar(20) DEFAULT 'TRC20' COMMENT '网络类型：TRC20/ERC20等',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-正常，0-删除',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_address` (`user_id`, `address`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户USDT地址表';



