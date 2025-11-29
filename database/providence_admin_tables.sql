-- Providence 后台管理系统数据表

-- 系统配置表
CREATE TABLE IF NOT EXISTS `pd_system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL COMMENT '配置键',
  `value` text COMMENT '配置值(JSON格式)',
  `description` varchar(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- 管理员操作日志表
CREATE TABLE IF NOT EXISTS `pd_admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '管理员ID',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `detail` text COMMENT '操作详情',
  `ip` varchar(50) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '浏览器信息',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员操作日志';

-- 公告表
CREATE TABLE IF NOT EXISTS `pd_notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `type` varchar(20) DEFAULT 'notice' COMMENT '类型: notice公告, activity活动',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 0禁用 1启用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `start_time` datetime DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- KYC认证表
CREATE TABLE IF NOT EXISTS `pd_kyc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `real_name` varchar(50) NOT NULL COMMENT '真实姓名',
  `id_card` varchar(30) NOT NULL COMMENT '身份证号',
  `id_card_front` varchar(255) DEFAULT NULL COMMENT '身份证正面',
  `id_card_back` varchar(255) DEFAULT NULL COMMENT '身份证背面',
  `face_photo` varchar(255) DEFAULT NULL COMMENT '人脸照片',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态: 0待审核 1通过 2拒绝',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因',
  `verified_at` datetime DEFAULT NULL COMMENT '认证时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `id_card` (`id_card`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='KYC认证表';

-- 收款账户表
CREATE TABLE IF NOT EXISTS `pd_payment_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL COMMENT '类型: bank银行卡, usdt',
  `bank_name` varchar(100) DEFAULT NULL COMMENT '银行名称',
  `account_name` varchar(100) DEFAULT NULL COMMENT '账户名',
  `account_number` varchar(100) DEFAULT NULL COMMENT '账号',
  `branch` varchar(200) DEFAULT NULL COMMENT '支行',
  `network` varchar(20) DEFAULT NULL COMMENT 'USDT网络: TRC20, ERC20',
  `address` varchar(200) DEFAULT NULL COMMENT 'USDT地址',
  `qr_code` varchar(255) DEFAULT NULL COMMENT '二维码',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态: 0禁用 1启用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收款账户表';

-- 插入默认配置
INSERT INTO `pd_system_config` (`key`, `value`, `description`, `created_at`) VALUES
('vip_config', '{"levels":[{"level":0,"name":"VIP0","min_invest":0,"interest_bonus":0,"referral_rate_1":1,"referral_rate_2":0}]}', 'VIP等级配置', NOW()),
('team_rewards', '[]', '团队奖励配置', NOW()),
('checkin_config', '{"daily_points":10,"continuous_7_bonus":50,"continuous_15_bonus":150,"continuous_30_bonus":500}', '签到配置', NOW()),
('exchange_rate', '{"usdt_to_cny":7.25,"cny_to_usdt":7.35,"points_to_cny":0.1}', '汇率配置', NOW()),
('recharge_config', '{"min_amount":100,"max_amount":5000000,"bank_fee":0,"usdt_fee":0}', '充值配置', NOW()),
('withdraw_config', '{"min_amount":100,"max_amount":500000,"daily_limit":3,"fee_rate":2,"min_fee":5}', '提现配置', NOW()),
('ribao_config', '{"enabled":true,"min_transfer":100,"max_transfer":10000000,"daily_rate":0.08,"annual_rate":29.2}', '日利宝配置', NOW()),
('investment_config', '{"min_amount":100,"max_amount":10000000,"early_exit":false,"early_exit_fee":5}', '投资配置', NOW()),
('newbie_config', '{"register_points":100,"register_bonus":0,"trial_fund":888,"trial_days":7}', '新人福利配置', NOW()),
('system_config', '{"maintenance":false,"register_enabled":true,"login_enabled":true}', '系统设置', NOW());

-- 插入默认收款账户
INSERT INTO `pd_payment_accounts` (`type`, `bank_name`, `account_name`, `account_number`, `branch`, `status`, `created_at`) VALUES
('bank', '中国工商银行', '张三', '6222021234567890123', '北京分行', 1, NOW());

INSERT INTO `pd_payment_accounts` (`type`, `network`, `address`, `status`, `created_at`) VALUES
('usdt', 'TRC20', 'TXyZ1234567890abcdefghijk', 1, NOW());
