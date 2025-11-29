# 📋 后端真实存在的支付接口清单

**扫描日期**: 2025-11-24
**说明**: 基于路由文件和控制器代码，列出真实存在的接口

---

## ✅ 真实存在的接口

### 1. USDT 配置接口

**路由定义**: `/route/api.php` line 23-24
```php
Route::get('payment-v2/usdtconfig', 'app\api\controller\PaymentV2@usdtConfig');
Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');
```

**完整路径**:
- `/api/payment-v2/usdtconfig` (旧路径)
- `/api/pay/usdt-config` (新路径)

**控制器文件**: `/app/api/controller/PaymentV2.php`
**方法名**: `usdtConfig()` (line 100-113)
**HTTP 方法**: GET
**是否需要认证**: ❌ 否（无需登录）

**返回示例**:
```json
{
  "code": 0,
  "msg": "获取成功",
  "data": {
    "usdt_address": "TYourUsdtAddressHere",
    "min_amount": "10",
    "max_amount": "50000",
    "rate": "7.2",
    "network": "TRC20",
    "tips": [
      "请使用TRC20网络转账USDT",
      "最小充值金额：10 USDT",
      "到账时间：12个区块确认后（约3-5分钟）",
      "转账完成后请耐心等待，系统会自动检测"
    ]
  }
}
```

---

### 2. USDT 创建订单接口

**路由定义**: `/route/api.php` line 51, 57
```php
Route::post('payment-v2/usdtcreate', 'app\api\controller\PaymentV2@usdtCreate');
Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate');
```

**完整路径**:
- `/api/payment-v2/usdtcreate` (旧路径)
- `/api/pay/usdt-create` (新路径)

**控制器文件**: `/app/api/controller/PaymentV2.php`
**方法名**: `usdtCreate()` (line 118-147)
**HTTP 方法**: POST
**是否需要认证**: ✅ 是（需要登录）

**请求参数**:
```json
{
  "amount": 10
}
```

**返回示例**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RUSDT202511241200001234",
    "order_no": "RUSDT202511241200001234",
    "rechargeId": 125,
    "amount": 10.0,
    "status": 0,
    "payUrl": "TYourUsdtAddressHere",
    "address": "TYourUsdtAddressHere",
    "network": "TRC20",
    "rate": 7.2,
    "cnyAmount": 72.0,
    "cny_amount": 72.0
  }
}
```

---

### 3. USDT 查询订单接口

**路由定义**: `/route/api.php` line 52, 58
```php
Route::get('payment-v2/usdtcheck', 'app\api\controller\PaymentV2@usdtCheck');
Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');
```

**完整路径**:
- `/api/payment-v2/usdtcheck` (旧路径)
- `/api/pay/usdt-check` (新路径)

**控制器文件**: `/app/api/controller/PaymentV2.php`
**方法名**: `usdtCheck()` (line 152-176)
**HTTP 方法**: GET
**是否需要认证**: ✅ 是（需要登录）

**请求参数**:
```
order_no=RUSDT202511241200001234
```

**返回示例**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RUSDT202511241200001234",
    "order_no": "RUSDT202511241200001234",
    "amount": 10.0,
    "status": 1,
    "paidAt": "2025-11-24 12:00:00",
    "paid_at": "2025-11-24 12:00:00"
  }
}
```

---

### 4. 微信支付创建接口

**路由定义**: `/route/api.php` line 49, 55
```php
Route::post('payment-v2/wechat', 'app\api\controller\PaymentV2@wechat');
Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');
```

**完整路径**:
- `/api/payment-v2/wechat` (旧路径)
- `/api/pay/wechat-create` (新路径)

**控制器文件**: `/app/api/controller/PaymentV2.php`
**方法名**: `wechat()` (line 16-53)
**HTTP 方法**: POST
**是否需要认证**: ✅ 是（需要登录）

**请求参数**:
```json
{
  "amount": 100
}
```

**返回示例**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RWX202511241200001234",
    "merchantOrderNo": "RWX202511241200001234",
    "systemOrderNo": "SP202511241200001234",
    "rechargeId": 123,
    "payUrl": "https://sevenpay.lh.plus/pay/...",
    "expireTime": 1763999999,
    "amount": 100.0,
    "status": 0
  }
}
```

---

### 5. 支付宝支付创建接口

**路由定义**: `/route/api.php` line 50, 56
```php
Route::post('payment-v2/alipay', 'app\api\controller\PaymentV2@alipay');
Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');
```

**完整路径**:
- `/api/payment-v2/alipay` (旧路径)
- `/api/pay/alipay-create` (新路径)

**控制器文件**: `/app/api/controller/PaymentV2.php`
**方法名**: `alipay()` (line 58-95)
**HTTP 方法**: POST
**是否需要认证**: ✅ 是（需要登录）

**请求参数**:
```json
{
  "amount": 100
}
```

**返回示例**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RAL202511241200001234",
    "merchantOrderNo": "RAL202511241200001234",
    "systemOrderNo": "SP202511241200001234",
    "rechargeId": 124,
    "payUrl": "https://sevenpay.lh.plus/pay/...",
    "expireTime": 1763999999,
    "amount": 100.0,
    "status": 0
  }
}
```

---

## 📊 接口汇总表

| 功能 | 旧路径 | 新路径 | 控制器方法 | 文件位置 | 认证 |
|------|--------|--------|-----------|---------|------|
| **USDT 配置** | `/api/payment-v2/usdtconfig` | `/api/pay/usdt-config` | `PaymentV2@usdtConfig` | line 100 | ❌ |
| **USDT 创建** | `/api/payment-v2/usdtcreate` | `/api/pay/usdt-create` | `PaymentV2@usdtCreate` | line 118 | ✅ |
| **USDT 查询** | `/api/payment-v2/usdtcheck` | `/api/pay/usdt-check` | `PaymentV2@usdtCheck` | line 152 | ✅ |
| **微信支付** | `/api/payment-v2/wechat` | `/api/pay/wechat-create` | `PaymentV2@wechat` | line 16 | ✅ |
| **支付宝支付** | `/api/payment-v2/alipay` | `/api/pay/alipay-create` | `PaymentV2@alipay` | line 58 | ✅ |

---

## ⚠️ 重要说明

### 路由状态

**路由定义存在** ✅:
- 所有接口在 `/route/api.php` 中都有路由定义
- 控制器方法都存在且实现完整

**但用户反馈** ❌:
- `/api/payment-v2/*` 路径返回 404
- 说明这些路由可能没有生效（缓存问题或路由加载问题）

### 建议

1. **如果旧路径不工作**，尝试新路径：
   - `/api/pay/usdt-config`
   - `/api/pay/usdt-create`
   - `/api/pay/usdt-check`
   - `/api/pay/wechat-create`
   - `/api/pay/alipay-create`

2. **如果新路径也不工作**，可能需要：
   - 清除 ThinkPHP 路由缓存
   - 重启 PHP-FPM
   - 检查路由文件是否正确加载

---

## 🔍 其他发现的控制器

### Payment 控制器（未在路由中定义）

**文件**: `/app/api/controller/Payment.php`

**方法**:
- `wechatPay()` - 微信支付下单
- `alipay()` - 支付宝支付下单
- `getUsdtAddress()` - 获取USDT充值地址
- `checkUsdtPayment()` - 检查USDT到账

**注意**: 这些方法在路由文件中**没有定义**，如果 ThinkPHP 支持自动路由，可能可以通过 `/api/Payment/getUsdtAddress` 访问，但**不建议使用**，因为路由未明确定义。

---

## ✅ 总结

**真实存在的接口**（基于路由定义）:
1. ✅ `/api/payment-v2/usdtconfig` 或 `/api/pay/usdt-config`
2. ✅ `/api/payment-v2/usdtcreate` 或 `/api/pay/usdt-create`
3. ✅ `/api/payment-v2/usdtcheck` 或 `/api/pay/usdt-check`
4. ✅ `/api/payment-v2/wechat` 或 `/api/pay/wechat-create`
5. ✅ `/api/payment-v2/alipay` 或 `/api/pay/alipay-create`

**所有接口都指向**: `app\api\controller\PaymentV2` 控制器

---

**文档生成完成！** 📝
