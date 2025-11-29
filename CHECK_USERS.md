# 📋 Providence 当前用户列表

**数据库**: providence  
**登录方式**: 使用 `username` 字段登录（不是手机号！）

---

## 👥 可用用户列表

$(mysql -u root -e "USE providence; 
SELECT 
    CONCAT('### ', username) as info,
    CONCAT('- **用户名**: \`', username, '\`') as u,
    CONCAT('- **UID**: ', uid) as uid_info,
    CONCAT('- **余额CNY**: ', FORMAT(balance_cny, 2)) as cny,
    CONCAT('- **余额USDT**: ', FORMAT(balance_usdt, 2)) as usdt,
    CONCAT('- **VIP等级**: ', vip_level) as vip,
    CONCAT('- **状态**: ', IF(status=1, '✅ 启用', '❌ 禁用')) as st,
    CONCAT('- **最后活跃**: ', IFNULL(last_active_at, '从未登录')) as last,
    '' as sep
FROM users 
ORDER BY id;" 2>/dev/null | tail -n +2 | sed 's/\t/\n/g')

---

## 🔐 如何查看密码

密码都是bcrypt加密的，需要查看测试账号SQL文件：

\`\`\`bash
cat /www/wwwroot/api.4kp3l0iq.top/测试账号.sql
\`\`\`

或者重置密码为已知密码：

\`\`\`sql
-- 重置为密码: 123456
UPDATE users 
SET password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'G138688';
\`\`\`

---

## 🎯 登录示例

**正确方式**（使用用户名）:
\`\`\`json
{
  "username": "G138688",
  "password": "你的密码"
}
\`\`\`

**错误方式**（使用手机号）:
\`\`\`json
{
  "username": "13800138688",  // ❌ 错误！现在不支持手机号登录
  "password": "你的密码"
}
\`\`\`

---

## 📊 用户统计

$(mysql -u root -e "USE providence; 
SELECT 
    COUNT(*) as '总用户数',
    SUM(IF(status=1, 1, 0)) as '启用',
    SUM(IF(status=0, 1, 0)) as '禁用',
    SUM(IF(is_online=1, 1, 0)) as '在线',
    FORMAT(SUM(balance_cny), 2) as '总余额CNY',
    FORMAT(SUM(balance_usdt), 2) as '总余额USDT'
FROM users;" 2>/dev/null | tail -n +2)

---

**登录测试**: https://4kp3l0iq.top/test-login.html  
**诊断工具**: https://4kp3l0iq.top/diagnose-login.html
