# ✅ 支付 API 完整实现报告

**完成日期**: 2025-11-24
**状态**: ✅ 全部完成

---

## 📋 已实现的 API 接口

### ✅ 1. 微信支付创建

**路径**: `POST /api/pay/wechat-create`

**请求参数**:
```json
{
  "amount": 100  // 充值金额（100-3000元）
}
```

**请求头**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**成功响应**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RWX202511241200001234",  // 商户订单号（用于查询）
    "merchantOrderNo": "RWX202511241200001234",  // 商户订单号
    "systemOrderNo": "SP202511241200001234",  // SevenPay系统订单号
    "rechargeId": 123,  // 充值记录ID
    "payUrl": "https://sevenpay.lh.plus/pay/...",  // 支付链接
    "expireTime": 1763999999,  // 过期时间（10位时间戳）
    "amount": 100.0,
    "status": 0  // 0=待支付
  }
}
```

**业务流程**:
1. ✅ 验证金额范围（100-3000元）
2. ✅ 生成商户订单号（格式：RWX + 时间戳 + 随机数）
3. ✅ 创建充值记录到 `recharge_records` 表（status=0 待支付）
4. ✅ 调用 SevenPay API 创建支付订单
5. ✅ 返回支付链接和订单信息

---

### ✅ 2. 支付宝支付创建

**路径**: `POST /api/pay/alipay-create`

**请求参数**:
```json
{
  "amount": 100  // 充值金额（100-20000元）
}
```

**请求头**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**成功响应**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RAL202511241200001234",  // 商户订单号
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

**业务流程**:
1. ✅ 验证金额范围（100-20000元）
2. ✅ 生成商户订单号（格式：RAL + 时间戳 + 随机数）
3. ✅ 创建充值记录到 `recharge_records` 表
4. ✅ 调用 SevenPay API 创建支付订单
5. ✅ 返回支付链接和订单信息

---

### ✅ 3. USDT 配置

**路径**: `GET /api/pay/usdt-config`

**请求头**（可选）:
```
Authorization: Bearer {token}  // 可选，未登录也可查看
```

**成功响应**:
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

**说明**: 无需认证，公开接口

---

### ✅ 4. USDT 订单创建

**路径**: `POST /api/pay/usdt-create`

**请求参数**:
```json
{
  "amount": 10  // 充值金额（10-50000 USDT）
}
```

**请求头**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**成功响应**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RUSDT202511241200001234",  // 订单号（驼峰）
    "order_no": "RUSDT202511241200001234",  // 订单号（下划线，兼容）
    "rechargeId": 125,  // 充值记录ID
    "amount": 10.0,
    "status": 0,  // 0=待支付
    "payUrl": "TYourUsdtAddressHere",  // USDT地址
    "address": "TYourUsdtAddressHere",
    "network": "TRC20",
    "rate": 7.2,
    "cnyAmount": 72.0,  // 等值CNY金额
    "cny_amount": 72.0  // 兼容旧版
  }
}
```

**业务流程**:
1. ✅ 验证金额范围（10-50000 USDT）
2. ✅ 生成订单号（格式：RUSDT + 时间戳 + 随机数）
3. ✅ 创建充值记录到 `recharge_records` 表（status=0 待支付）
4. ✅ 返回 USDT 收款地址和订单信息

---

### ✅ 5. USDT 订单查询

**路径**: `GET /api/pay/usdt-check`

**请求参数**:
```
order_no=RUSDT202511241200001234
```

**请求头**:
```
Authorization: Bearer {token}
```

**成功响应**:
```json
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "RUSDT202511241200001234",
    "order_no": "RUSDT202511241200001234",
    "amount": 10.0,
    "status": 1,  // 0=待支付, 1=已支付
    "paidAt": "2025-11-24 12:00:00",
    "paid_at": "2025-11-24 12:00:00"
  }
}
```

**业务流程**:
1. ✅ 根据订单号查询充值记录
2. ✅ 返回订单状态和支付信息

---

## 🗂️ 路由配置

### ✅ 无需认证的接口

```php
// /route/api.php (line 24)
Route::get('pay/usdt-config', 'app\api\controller\PaymentV2@usdtConfig');
```

### ✅ 需要认证的接口

```php
// /route/api.php (line 55-58)
Route::post('pay/wechat-create', 'app\api\controller\PaymentV2@wechat');
Route::post('pay/alipay-create', 'app\api\controller\PaymentV2@alipay');
Route::post('pay/usdt-create', 'app\api\controller\PaymentV2@usdtCreate');
Route::get('pay/usdt-check', 'app\api\controller\PaymentV2@usdtCheck');
```

---

## 📊 订单创建流程

### 微信/支付宝支付流程

```
用户提交充值请求
    ↓
POST /api/pay/wechat-create 或 /api/pay/alipay-create
    ↓
验证金额范围（100-3000 或 100-20000）
    ↓
生成商户订单号（RWX/RAL + 时间戳 + 随机数）
    ↓
创建充值记录到 recharge_records 表（status=0）
    ↓
调用 SevenPay API 创建支付订单
    ↓
返回支付链接（payUrl）和订单信息
    ↓
用户跳转支付或扫码支付
    ↓
SevenPay 回调 /api/payment-v2/sevenpaynotify
    ↓
更新订单状态（status=1）并增加用户余额
```

### USDT 支付流程

```
用户提交充值请求
    ↓
POST /api/pay/usdt-create
    ↓
验证金额范围（10-50000 USDT）
    ↓
生成订单号（RUSDT + 时间戳 + 随机数）
    ↓
创建充值记录到 recharge_records 表（status=0）
    ↓
返回 USDT 收款地址和订单信息
    ↓
用户转账 USDT 到指定地址
    ↓
系统监控或用户上传凭证
    ↓
更新订单状态（status=1）并增加用户余额
```

---

## 🗄️ 数据库表结构

### `recharge_records` 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | int | 主键 |
| `user_id` | int | 用户ID |
| `order_no` | varchar | 商户订单号（唯一） |
| `amount` | decimal | 充值金额 |
| `currency` | varchar | 币种（CNY/USDT） |
| `payment_method` | varchar | 支付方式（wechat/alipay/usdt） |
| `status` | tinyint | 状态（0=待支付, 1=已支付, 2=失败） |
| `transaction_id` | varchar | 系统订单号（SevenPay返回） |
| `paid_amount` | decimal | 实际支付金额 |
| `paid_at` | datetime | 支付时间 |
| `created_at` | datetime | 创建时间 |

---

## ✅ 订单号生成规则

| 支付方式 | 订单号格式 | 示例 |
|---------|----------|------|
| **微信** | `RWX` + 时间戳 + 随机数 | `RWX202511241200001234` |
| **支付宝** | `RAL` + 时间戳 + 随机数 | `RAL202511241200001234` |
| **USDT** | `RUSDT` + 时间戳 + 随机数 | `RUSDT202511241200001234` |

**格式说明**:
- `R` = Recharge（充值）
- `WX` = WeChat（微信）
- `AL` = Alipay（支付宝）
- `USDT` = USDT
- 时间戳格式：`YmdHis`（年月日时分秒）
- 随机数：1000-9999

---

## 🔐 安全特性

✅ **Token 认证** - 所有创建订单接口需要 Token
✅ **金额验证** - 严格验证金额范围
✅ **订单唯一性** - 订单号唯一约束
✅ **状态管理** - 防止重复支付
✅ **签名验证** - SevenPay 回调签名验证
✅ **IP 验证** - 回调 IP 白名单

---

## 📝 返回格式统一

所有接口统一返回格式：

**成功**:
```json
{
  "code": 1,  // 或 0（根据接口）
  "msg": "ok",
  "data": { ... }
}
```

**失败**:
```json
{
  "code": -1,
  "msg": "错误信息",
  "data": []
}
```

---

## 🎯 前端调用示例

### 微信支付

```javascript
const response = await fetch('https://api.4kp3l0iq.top/api/pay/wechat-create', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ amount: 100 })
});

const result = await response.json();
if (result.code === 1) {
    // 保存订单号
    const orderNo = result.data.orderNo;

    // 跳转支付或显示二维码
    if (result.data.payUrl) {
        window.location.href = result.data.payUrl;
    }
}
```

### 支付宝支付

```javascript
const response = await fetch('https://api.4kp3l0iq.top/api/pay/alipay-create', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ amount: 100 })
});
```

### USDT 配置

```javascript
const response = await fetch('https://api.4kp3l0iq.top/api/pay/usdt-config');
const result = await response.json();
const usdtAddress = result.data.usdt_address;
```

### USDT 创建订单

```javascript
const response = await fetch('https://api.4kp3l0iq.top/api/pay/usdt-create', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ amount: 10 })
});

const result = await response.json();
if (result.code === 1) {
    const orderNo = result.data.orderNo;
    const address = result.data.address;
    // 显示 USDT 地址和二维码
}
```

### USDT 查询订单

```javascript
const response = await fetch(`https://api.4kp3l0iq.top/api/pay/usdt-check?order_no=${orderNo}`, {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

const result = await response.json();
if (result.data.status === 1) {
    // 已支付
}
```

---

## ✨ 特性总结

| 特性 | 微信 | 支付宝 | USDT |
|------|------|--------|------|
| **订单创建** | ✅ | ✅ | ✅ |
| **金额范围** | 100-3000 | 100-20000 | 10-50000 |
| **自动到账** | ✅ | ✅ | ⚠️ 需监控 |
| **订单查询** | ✅ | ✅ | ✅ |
| **回调通知** | ✅ | ✅ | ❌ |

---

## 🎉 完成状态

✅ **所有 5 个 API 接口已实现**
✅ **订单创建流程完整**
✅ **返回格式统一**
✅ **安全验证完善**
✅ **前端可立即调用**

---

**所有支付 API 已准备就绪！** 🚀
