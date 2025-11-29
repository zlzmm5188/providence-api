# 🔴 支付接口 404 问题 - 最终诊断报告

**日期**: 2025-11-25
**问题**: 所有 `/api/pay/*` 接口返回 404

---

## ✅ 已确认的事实

### 1. 路由定义存在 ✅

**路由文件**: `/route/api.php`

所有支付接口路由都已正确定义：
```php
// 无需认证
Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');  // line 24

// 需要认证
Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');    // line 55
Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');   // line 56
Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate'); // line 57
Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');    // line 58
```

### 2. 路由已加载 ✅

**验证命令**: `php think route:list`

**输出结果**:
```
| api/pay/usdt-config     | app\api\controller\PaymentV2@usdtConfig | get  |
| api/pay/wechat-create   | app\api\controller\PaymentV2@wechat     | post |
| api/pay/alipay-create   | app\api\controller\PaymentV2@alipay     | post |
| api/pay/usdt-create     | app\api\controller\PaymentV2@usdtCreate | post |
| api/pay/usdt-check      | app\api\controller\PaymentV2@usdtCheck  | get  |
```

**结论**: ✅ 所有路由都已正确加载到路由表中

### 3. 控制器和方法存在 ✅

**控制器文件**: `/app/api/controller/PaymentV2.php` (7.4K)

**方法列表**:
- ✅ `usdtConfig()` - line 100-113
- ✅ `wechat()` - line 16-53
- ✅ `alipay()` - line 58-95
- ✅ `usdtCreate()` - line 118-147
- ✅ `usdtCheck()` - line 152-176

### 4. 对比测试 ✅

**正常工作的接口**:
- `/api/project/index` → ✅ 返回 200，JSON数据正常

**不工作的接口**:
- `/api/pay/usdt-config` → ❌ 返回 404
- `/api/pay/wechat-create` → ❌ 返回 404
- `/api/pay/alipay-create` → ❌ 返回 404
- `/api/pay/usdt-create` → ❌ 返回 404
- `/api/pay/usdt-check` → ❌ 返回 404

---

## 🔍 问题分析

### 核心矛盾

1. ✅ **路由定义存在** - 所有路由都在 `route/api.php` 中定义
2. ✅ **路由已加载** - `php think route:list` 显示所有路由
3. ✅ **控制器存在** - PaymentV2.php 文件和方法都正常
4. ✅ **路由系统正常** - `/api/project/index` 能正常工作
5. ❌ **支付路由不工作** - 所有 `/api/pay/*` 接口返回 404

### 可能的原因

#### 1. Nginx 配置问题 ⚠️

**发现的问题**:
- Nginx 配置中有 `location /api/` 使用 `proxy_pass`，可能导致循环代理
- 错误日志显示尝试连接 `/tmp/php-cgi-82.sock`（不存在）

**当前配置**:
```nginx
location /api/ {
    proxy_pass http://api.4kp3l0iq.top:80/api/;
    ...
}
```

**问题**: 如果请求通过 HTTPS（443）进入，会被代理到 HTTP（80），可能导致路由匹配问题。

#### 2. ThinkPHP 路由匹配优先级 ⚠️

**路由分组结构**:
```php
Route::group('api', function () {
    Route::get('pay/usdt-config', ...);  // 在"无需登录"分组中
});

Route::group('api', function () {
    Route::post('pay/wechat-create', ...);  // 在"需要登录"分组中
})->middleware(AuthMiddleware::class);
```

**可能的问题**:
- 路由匹配顺序可能有问题
- 中间件可能影响了路由匹配

#### 3. CloudFlare 缓存 ⚠️

- CDN 可能缓存了 404 响应
- 需要清除 CloudFlare 缓存

---

## 🎯 解决方案

### 方案 1: 检查并修复 Nginx 配置（推荐）

```bash
# 1. 检查完整的 Nginx 配置
cat /etc/nginx/sites-enabled/* | grep -A 50 "api.4kp3l0iq.top"

# 2. 确认 PHP-FPM socket 路径正确
# 当前运行: /run/php/php8.3-fpm.sock
# Nginx配置: unix:/run/php/php-fpm.sock (应该正确)

# 3. 检查是否有循环代理
# 如果发现 proxy_pass 指向自己，需要修复
```

### 方案 2: 清除所有缓存

```bash
# 1. 清除 ThinkPHP 缓存
rm -rf /www/wwwroot/api.4kp3l0iq.top/runtime/*

# 2. 清除 CloudFlare 缓存
# 访问 CloudFlare 后台 → 缓存 → 清除所有缓存

# 3. 重启服务
systemctl restart php8.3-fpm
systemctl reload nginx
```

### 方案 3: 使用自动路由测试

如果路由仍然不工作，可以尝试使用 ThinkPHP 的自动路由：

```bash
# 测试自动路由格式
curl "https://api.4kp3l0iq.top/api/PaymentV2/usdtConfig"
```

### 方案 4: 检查路由匹配顺序

可能需要调整路由定义的顺序，将支付路由放在更前面的位置。

---

## 📋 需要执行的检查

### 1. 检查 Nginx 完整配置

```bash
cat /etc/nginx/sites-enabled/* | grep -B 10 -A 50 "api.4kp3l0iq.top"
```

### 2. 检查 ThinkPHP 错误日志

```bash
tail -50 /www/wwwroot/api.4kp3l0iq.top/runtime/log/$(date +%Y%m%d)/*.log
```

### 3. 检查 Nginx 访问日志

```bash
tail -50 /www/wwwlogs/api.4kp3l0iq.top.log | grep "pay"
```

### 4. 测试直接访问控制器

```bash
# 测试自动路由
curl "https://api.4kp3l0iq.top/api/PaymentV2/usdtConfig"
```

---

## 📊 当前状态总结

| 项目 | 状态 | 说明 |
|------|------|------|
| **路由定义** | ✅ 正常 | 所有路由都在 route/api.php 中定义 |
| **路由加载** | ✅ 正常 | php think route:list 显示所有路由 |
| **控制器文件** | ✅ 正常 | PaymentV2.php 存在且完整 |
| **控制器方法** | ✅ 正常 | 所有方法都已实现 |
| **路由系统** | ✅ 正常 | /api/project/index 能正常工作 |
| **支付路由访问** | ❌ 404 | 所有 /api/pay/* 接口返回 404 |
| **PHP-FPM** | ✅ 正常 | php8.3-fpm 运行中 |
| **Nginx** | ⚠️ 可疑 | 配置中可能有循环代理 |

---

## 🔴 最终诊断

**问题**: 路由定义和加载都正常，但实际访问时路由未生效。

**最可能的原因**:
1. **Nginx 配置问题** - 循环代理或路由转发问题
2. **ThinkPHP 路由匹配优先级** - 路由规则可能被其他规则覆盖
3. **CloudFlare 缓存** - CDN 缓存了 404 响应

**建议优先级**:
1. 🔴 **立即检查 Nginx 配置** - 确认是否有循环代理或路由转发问题
2. 🟡 **清除 CloudFlare 缓存** - 排除 CDN 缓存问题
3. 🟡 **检查 ThinkPHP 路由匹配顺序** - 确认路由规则优先级

---

**诊断报告生成完成！** 📋
