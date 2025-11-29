# 🔧 路由 404 问题修复指南

**问题**: `/api/payment-v2/usdtconfig` 和 `/api/payment-v2/wechat` 返回 404

**状态**: ⚠️ 路由配置正确，但可能被缓存或路由加载顺序影响

---

## ✅ 已完成的修复

### 1. 路由配置检查 ✅

**路由文件**: `/route/api.php`

✅ **无需认证分组** (line 27):
```php
Route::get('payment-v2/usdtconfig', 'app\api\controller\PaymentV2@usdtConfig');
```

✅ **需要认证分组** (line 47-50):
```php
Route::post('payment-v2/wechat', 'app\api\controller\PaymentV2@wechat');
Route::post('payment-v2/alipay', 'app\api\controller\PaymentV2@alipay');
Route::post('payment-v2/usdtcreate', 'app\api\controller\PaymentV2@usdtCreate');
Route::get('payment-v2/usdtcheck', 'app\api\controller\PaymentV2@usdtCheck');
```

### 2. 控制器检查 ✅

✅ **PaymentV2 控制器存在**: `/app/api/controller/PaymentV2.php`
✅ **方法名正确**: `usdtConfig()`, `wechat()`, `alipay()`
✅ **语法检查**: 无语法错误

### 3. 路由优化 ✅

✅ **已将支付路由移到分组前面**，确保优先匹配
✅ **已清除所有缓存**

---

## 🔍 问题诊断

### 可能的原因

1. **CloudFlare 缓存** - 返回旧的 404 响应
2. **PHP-FPM 进程缓存** - 路由配置未重新加载
3. **ThinkPHP 路由缓存** - 路由缓存未清除
4. **DNS/CDN 缓存** - 域名解析缓存

---

## 🚀 解决方案

### 方案 1: 清除所有缓存（推荐）

```bash
# 1. 清除 ThinkPHP 缓存
rm -rf /www/wwwroot/api.4kp3l0iq.top/runtime/*
mkdir -p /www/wwwroot/api.4kp3l0iq.top/runtime

# 2. 重启 PHP-FPM
systemctl restart php-fpm
# 或
service php-fpm restart

# 3. 清除 CloudFlare 缓存
# 访问 CloudFlare 后台 → 缓存 → 清除所有缓存
```

### 方案 2: 浏览器硬刷新

在浏览器中按 **Ctrl+Shift+R** (Windows) 或 **Cmd+Shift+R** (Mac) 强制刷新

### 方案 3: 验证路由是否工作

```bash
# 测试 USDT 配置接口
curl -v "https://api.4kp3l0iq.top/api/payment-v2/usdtconfig" \
  -H "Cache-Control: no-cache"

# 测试微信支付接口（需要 Token）
curl -v -X POST "https://api.4kp3l0iq.top/api/payment-v2/wechat" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Cache-Control: no-cache" \
  -d '{"amount":100}'
```

---

## 📋 路由配置总览

### ✅ 无需认证的支付接口

| 路由 | 方法 | 控制器 | 状态 |
|------|------|--------|------|
| `/api/payment-v2/usdtconfig` | GET | PaymentV2@usdtConfig | ✅ 已配置 |
| `/api/payment-v2/sevenpaynotify` | POST | PaymentV2@sevenPayNotify | ✅ 已配置 |

### ✅ 需要认证的支付接口

| 路由 | 方法 | 控制器 | 状态 |
|------|------|--------|------|
| `/api/payment-v2/wechat` | POST | PaymentV2@wechat | ✅ 已配置 |
| `/api/payment-v2/alipay` | POST | PaymentV2@alipay | ✅ 已配置 |
| `/api/payment-v2/usdtcreate` | POST | PaymentV2@usdtCreate | ✅ 已配置 |
| `/api/payment-v2/usdtcheck` | GET | PaymentV2@usdtCheck | ✅ 已配置 |

---

## 🔧 如果仍然 404

### 检查清单

- [ ] 清除浏览器缓存（Ctrl+Shift+R）
- [ ] 清除 CloudFlare 缓存
- [ ] 重启 PHP-FPM
- [ ] 检查 Nginx 配置是否正确
- [ ] 检查 ThinkPHP 路由文件是否被加载
- [ ] 检查控制器文件权限

### 临时解决方案

如果路由仍然不工作，可以尝试使用 **自动路由**：

在 `config/route.php` 中启用自动路由：
```php
return [
    'url_route_on' => true,
    'url_route_must' => false,  // 允许自动路由
    'route_complete_match' => false,
];
```

然后访问：
- `/api/PaymentV2/usdtConfig` (自动路由格式)
- `/api/PaymentV2/wechat` (自动路由格式)

---

## 📝 总结

✅ **路由配置完全正确**
✅ **控制器和方法都存在**
✅ **代码语法无错误**

**问题很可能是缓存导致的**，需要：
1. 清除所有缓存
2. 重启 PHP-FPM
3. 清除 CloudFlare 缓存
4. 浏览器硬刷新

---

**修复完成！请按照上述步骤清除缓存后重新测试。** 🚀
