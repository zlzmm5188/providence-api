# 🔍 路由 404 问题诊断报告

**问题**: `/api/pay/usdt-config` 和 `/api/pay/wechat-create` 返回 404

**日期**: 2025-11-24

---

## ✅ 已完成的修复

### 1. 路由配置 ✅

**文件**: `/route/api.php`

✅ **无需认证分组** (line 24):
```php
Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');
```

✅ **需要认证分组** (line 55-58):
```php
Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');
Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');
Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate');
Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');
```

### 2. 控制器方法 ✅

✅ **PaymentV2 控制器存在**: `/app/api/controller/PaymentV2.php`
✅ **方法存在**: `usdtConfig()`, `wechat()`, `alipay()`, `usdtCreate()`, `usdtCheck()`
✅ **语法检查**: 无语法错误

### 3. 前端请求头修复 ✅

✅ **已修复**: 使用 `Authorization: Bearer {token}` 替代 `Token: {token}`

---

## 🔍 问题诊断

### 测试结果

| 接口 | 路径 | 状态 | 说明 |
|------|------|------|------|
| `/api/project/index` | ✅ 工作 | 返回 JSON | 路由系统正常 |
| `/api/pay/usdt-config` | ❌ 404 | 返回 HTML | 路由未识别 |
| `/api/payment-v2/usdtconfig` | ❌ 404 | 返回 HTML | 旧路径也不工作 |
| `/api/pay/wechat-create` | ❌ 404 | 返回 HTML | 路由未识别 |

### 可能的原因

1. **ThinkPHP 路由缓存问题** - 路由缓存未清除
2. **路由加载顺序问题** - 路由文件加载顺序可能有问题
3. **PHP-FPM 进程缓存** - PHP-FPM 进程可能缓存了旧的路由配置
4. **CloudFlare 缓存** - CDN 可能缓存了 404 响应

---

## 🚀 解决方案

### 方案 1: 清除所有缓存（推荐）

```bash
# 1. 清除 ThinkPHP 运行时缓存
rm -rf /www/wwwroot/api.4kp3l0iq.top/runtime/*
mkdir -p /www/wwwroot/api.4kp3l0iq.top/runtime/{cache,log,temp}
chmod -R 777 /www/wwwroot/api.4kp3l0iq.top/runtime

# 2. 重启 PHP-FPM
systemctl restart php-fpm
# 或
service php-fpm restart

# 3. 清除 CloudFlare 缓存
# 访问: https://dash.cloudflare.com → 选择域名 → 缓存 → 清除所有缓存
```

### 方案 2: 使用旧路径（临时方案）

如果新路径不工作，可以暂时使用旧路径：

**前端修改**:
```javascript
// 旧路径（已确认存在）
const apiPath = paymentMethod === 'wechat'
  ? '/api/payment-v2/wechat'  // 旧路径
  : '/api/payment-v2/alipay';  // 旧路径

// USDT 配置
const response = await fetch(`${API_BASE}/api/payment-v2/usdtconfig`, {
  headers: {
    "Authorization": token ? `Bearer ${token}` : "",
  },
});
```

### 方案 3: 检查路由文件加载

检查 ThinkPHP 是否正确加载了路由文件：

```bash
# 检查路由文件是否存在
ls -la /www/wwwroot/api.4kp3l0iq.top/route/api.php

# 检查路由文件语法
php -l /www/wwwroot/api.4kp3l0iq.top/route/api.php

# 检查是否有其他路由文件冲突
ls -la /www/wwwroot/api.4kp3l0iq.top/route/
```

### 方案 4: 使用自动路由（临时方案）

如果路由仍然不工作，可以尝试使用 ThinkPHP 的自动路由：

**访问路径**:
- `/api/PaymentV2/usdtConfig` (自动路由格式)
- `/api/PaymentV2/wechat` (自动路由格式)

---

## 📋 验证步骤

### 1. 清除缓存后测试

```bash
# 清除缓存
rm -rf /www/wwwroot/api.4kp3l0iq.top/runtime/*

# 测试接口
curl -s "https://api.4kp3l0iq.top/api/pay/usdt-config" | head -1
curl -s -X POST "https://api.4kp3l0iq.top/api/pay/wechat-create" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"amount":100}' | head -1
```

### 2. 浏览器测试

1. 打开浏览器开发者工具（F12）
2. 清除浏览器缓存（Ctrl+Shift+R）
3. 访问充值页面
4. 查看 Network 标签页中的请求

### 3. 检查错误日志

```bash
# 查看 ThinkPHP 错误日志
tail -50 /www/wwwroot/api.4kp3l0iq.top/runtime/log/$(date +%Y%m%d)/*.log

# 查看认证中间件日志
tail -50 /www/wwwroot/api.4kp3l0iq.top/runtime/debug_auth.log
```

---

## 🔧 临时解决方案

如果路由仍然不工作，可以暂时使用旧路径：

### 前端修改

```javascript
// recharge.html

// USDT 配置 - 使用旧路径
const response = await fetch(`${API_BASE}/api/payment-v2/usdtconfig`, {
  headers: {
    "Authorization": token ? `Bearer ${token}` : "",
    "Content-Type": "application/json",
  },
});

// 微信/支付宝支付 - 使用旧路径
const apiPath = paymentMethod === 'wechat'
  ? '/api/payment-v2/wechat'  // 旧路径
  : '/api/payment-v2/alipay';  // 旧路径
```

---

## 📝 总结

✅ **路由配置正确**
✅ **控制器方法存在**
✅ **前端请求头已修复**
⚠️ **路由可能被缓存，需要清除缓存**

**建议操作**:
1. 清除所有缓存
2. 重启 PHP-FPM
3. 清除 CloudFlare 缓存
4. 浏览器硬刷新（Ctrl+Shift+R）

如果仍然不工作，可以暂时使用旧路径 `/api/payment-v2/...`。

---

**修复完成！请按照上述步骤清除缓存后重新测试。** 🚀
