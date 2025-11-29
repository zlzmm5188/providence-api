-- =============================================
-- Providence 测试账号创建脚本
-- =============================================

-- 1. 创建管理员账号（邀请码 00000001）
-- 用户名: admin
-- 密码: password
INSERT INTO users (username, password, invite_code, vip_level, status, created_at)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password
    '00000001',
    0,
    1,
    NOW()
);

-- 2. 创建测试用户1（邀请码 12345678）
-- 用户名: testuser1
-- 密码: 123456
INSERT INTO users (username, password, invite_code, vip_level, status, created_at)
VALUES (
    'testuser1',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- 123456
    '12345678',
    0,
    1,
    NOW()
);

-- 3. 创建测试用户2（邀请码 87654321）
-- 用户名: testuser2
-- 密码: 123456
INSERT INTO users (username, password, invite_code, vip_level, status, created_at)
VALUES (
    'testuser2',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- 123456
    '87654321',
    0,
    1,
    NOW()
);

-- 4. 创建测试投资项目
INSERT INTO invest_projects (
    name, currency, rate, cycle, total_amount, sold_amount, min_amount, max_amount,
    usdt_bonus, vip_limit, status, created_at
) VALUES
(
    '稳健理财30天',
    'CNY',
    '0.015000',  -- 1.5% 日利率
    30,  -- 30天
    '1000000.00000000',  -- 总额100万
    '0.00000000',  -- 已售0
    '1000.00000000',  -- 最小1000
    '50000.00000000',  -- 最大5万
    '0.000000',  -- USDT奖励
    0,  -- VIP限制
    1,  -- 上架
    NOW()
),
(
    '高收益理财60天',
    'CNY',
    '0.025000',  -- 2.5% 日利率
    60,  -- 60天
    '2000000.00000000',  -- 总额200万
    '0.00000000',  -- 已售0
    '5000.00000000',  -- 最小5000
    '100000.00000000',  -- 最大10万
    '0.000000',  -- USDT奖励
    1,  -- VIP1才能投
    1,  -- 上架
    NOW()
),
(
    'USDT全球理财90天',
    'USDT',
    '0.030000',  -- 3% 日利率
    90,  -- 90天
    '500000.00000000',  -- 总额50万USDT
    '0.00000000',  -- 已售0
    '1000.00000000',  -- 最小1000
    '50000.00000000',  -- 最大5万
    '0.005000',  -- USDT额外奖励 0.5%
    0,  -- VIP限制
    1,  -- 上架
    NOW()
);

-- 5. 初始化系统配置
INSERT INTO system_config (config_key, config_value, remark) VALUES
('require_invite_code', '1', '是否强制邀请码注册（0=否，1=是）'),
('withdraw_fee_cny', '10.00000000', 'CNY提现手续费'),
('withdraw_fee_usdt', '5.00000000', 'USDT提现手续费'),
('trial_fund_amount', '888.00000000', '体验金金额'),
('trial_fund_expire_days', '7', '体验金过期天数');

-- =============================================
-- 账号信息总结
-- =============================================

-- 管理员账号：
--   用户名: admin
--   密码: password
--   邀请码: 00000001

-- 测试用户1：
--   用户名: testuser1
--   密码: 123456
--   邀请码: 12345678

-- 测试用户2：
--   用户名: testuser2
--   密码: 123456
--   邀请码: 87654321
