#!/bin/bash
echo "======================================"
echo "Providence 数据库详细信息"
echo "======================================"
echo ""

echo "📊 数据库统计"
echo "--------------------------------------"
mysql -u root -e "
USE providence;
SELECT 
    '用户总数' as 项目, COUNT(*) as 数量 FROM users
UNION ALL
SELECT '项目数量', COUNT(*) FROM projects
UNION ALL
SELECT '投资订单', COUNT(*) FROM invest_orders
UNION ALL
SELECT '充值记录', COUNT(*) FROM recharge_records
UNION ALL
SELECT '提现记录', COUNT(*) FROM withdraw_records
UNION ALL
SELECT '日利宝记录', COUNT(*) FROM ribao_records
UNION ALL
SELECT '公告数量', COUNT(*) FROM announcements;
" 2>/dev/null

echo ""
echo "👥 用户账户余额"
echo "--------------------------------------"
mysql -u root -e "
USE providence;
SELECT 
    id as ID,
    username as 用户名,
    phone as 手机号,
    CONCAT(FORMAT(balance_cny, 2), ' CNY') as 人民币余额,
    CONCAT(FORMAT(balance_usdt, 2), ' USDT') as USDT余额,
    vip_level as VIP等级,
    CASE status WHEN 1 THEN '✅启用' ELSE '❌禁用' END as 状态
FROM users
ORDER BY id;
" 2>/dev/null

echo ""
echo "💰 项目列表"
echo "--------------------------------------"
mysql -u root -e "
USE providence;
SELECT 
    id as ID,
    name as 项目名称,
    CONCAT(daily_rate * 100, '%') as 日收益率,
    CONCAT(min_amount, '~', max_amount) as 投资范围,
    days as 周期天数,
    CASE status WHEN 1 THEN '✅上架' ELSE '❌下架' END as 状态
FROM projects
ORDER BY id;
" 2>/dev/null

echo ""
echo "🔐 测试账号信息"
echo "--------------------------------------"
mysql -u root -e "
USE providence;
SELECT 
    id,
    username,
    phone,
    email,
    invite_code as 邀请码,
    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as 创建时间
FROM users
WHERE id <= 11
ORDER BY id;
" 2>/dev/null

echo ""
echo "======================================"
