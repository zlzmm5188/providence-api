# ✅ SevenPay 对接修复完成报告

**修复日期**: 2025-11-24
**状态**: ✅ 全部完成

---

## 📋 修复内容清单

### ✅ 1. 支付网关地址修正

**问题**: 代码中使用 `/index.php/pay/create`，文档要求 `/api/pay/create`

**修复**:
```php
// 修改前
'payGateway' => 'https://sevenpay.lh.plus/index.php/pay/create',
'queryGateway' => 'https://sevenpay.lh.plus/index.php/pay/query',

// 修改后 ✅
'payGateway' => 'https://sevenpay.lh.plus/api/pay/create',
'queryGateway' => 'https://sevenpay.lh.plus/api/pay/query',
```

**文件**: `app/common/service/SevenPayService.php` (line 20-21)

---

### ✅ 2. 返回字段名统一为驼峰命名

**问题**: 返回字段使用下划线命名，文档要求驼峰命名

**修复**:
```php
// 修改前
'data' => [
    'order_no' => ...,
    'pay_url' => ...
]

// 修改后 ✅
'data' => [
    'orderNo' => ...,      // 系统订单号（驼峰）
    'payUrl' => ...,       // 支付链接（驼峰）
    'expireTime' => ...    // 过期时间（10位时间戳）
]
```

**文件**:
- `app/common/service/SevenPayService.php` (line 91-102)
- `app/api/controller/PaymentV2.php` (line 32-42, 66-76)

---

### ✅ 3. 回调处理返回小写 "success"

**问题**: 文档明确要求返回小写 `success`

**修复**:
```php
// 修改后 ✅
if ($result === 'SUCCESS') {
    echo 'success';  // 小写 success
} else {
    echo $result;
}
```

**文件**: `app/api/controller/PaymentV2.php` (line 166-175)

---

### ✅ 4. 回调参数字段名修正

**问题**: 回调参数使用驼峰命名，文档使用下划线命名

**修复**:
```php
// 修改前
$orderNo = $postData['merchantOrderNo'];

// 修改后 ✅
$merchantOrderNo = $postData['merchant_order_no'] ?? $postData['merchantOrderNo'] ?? '';
$systemOrderNo = $postData['order_no'] ?? $postData['orderNo'] ?? '';
$payAmount = $postData['pay_amount'] ?? $postData['payAmount'] ?? $amount;
$paymentTime = $postData['payment_time'] ?? $postData['paymentTime'] ?? time();
```

**文件**: `app/common/service/SevenPayService.php` (line 126-165)

---

### ✅ 5. 使用实际支付金额

**问题**: 充值金额应使用 `pay_amount`（实际支付金额），而不是 `amount`

**修复**:
```php
// 修改后 ✅
Db::name('users')->where('id', $recharge['user_id'])->inc('balance_cny', $payAmount)->update();
Db::name('users')->where('id', $recharge['user_id'])->inc('total_recharge_cny', $payAmount)->update();
```

**文件**: `app/common/service/SevenPayService.php` (line 168-182)

---

### ✅ 6. 回调地址修正

**问题**: 回调地址配置错误

**修复**:
```php
// 修改前
'notifyUrl' => request()->domain() . '/api/payment/sevenpay-notify',

// 修改后 ✅
'notifyUrl' => $domain . '/api/payment-v2/sevenpaynotify',
```

**文件**: `app/common/service/SevenPayService.php` (line 69)

---

### ✅ 7. 前端兼容性修复

**问题**: 前端需要兼容新的驼峰命名格式

**修复**:
```javascript
// 修改后 ✅
const payUrl = result.data.payUrl || result.data.pay_url;
const orderNo = result.data.orderNo || result.data.order_no;
const expireTime = result.data.expireTime || (Date.now() / 1000 + 30 * 60);
```

**文件**: `4kp3l0iq.top/recharge.html` (line 2037-2048)

---

## 📊 文档规范对照表

| 文档要求 | 修复前 | 修复后 | 状态 |
|---------|--------|--------|------|
| **支付网关** | `/index.php/pay/create` | `/api/pay/create` | ✅ |
| **查询网关** | `/index.php/pay/query` | `/api/pay/query` | ✅ |
| **返回字段** | `pay_url` / `order_no` | `payUrl` / `orderNo` | ✅ |
| **回调返回** | `SUCCESS` | `success` (小写) | ✅ |
| **回调参数** | `merchantOrderNo` | `merchant_order_no` | ✅ |
| **支付金额** | `amount` | `pay_amount` | ✅ |
| **回调地址** | `/api/payment/sevenpay-notify` | `/api/payment-v2/sevenpaynotify` | ✅ |

---

## 🔐 签名算法验证

✅ **签名算法已正确实现**:
1. 排除 `sign` 字段
2. 按 key 排序
3. 拼接成字符串 `key=value&key=value&`
4. 加上密钥 `key=API_KEY`
5. MD5 加密（小写）

**文件**: `app/common/service/SevenPayService.php` (line 209-229)

---

## 📡 接口对接状态

### ✅ 发起支付 - `/api/pay/create`

| 参数 | 状态 | 说明 |
|------|------|------|
| merchantCode | ✅ | M1763172182 |
| channelType | ✅ | S02(微信) / S01(支付宝) |
| merchantOrderNo | ✅ | 已生成 |
| amount | ✅ | 已验证范围 |
| notifyUrl | ✅ | 已修正 |
| returnUrl | ✅ | 已配置 |
| ip | ✅ | 用户IP |
| title | ✅ | "Providence充值" |
| describe | ✅ | "用户充值" |
| sign | ✅ | MD5签名 |

### ✅ 回调通知 - `/api/payment-v2/sevenpaynotify`

| 处理项 | 状态 | 说明 |
|--------|------|------|
| IP验证 | ✅ | 23.94.207.16 |
| 签名验证 | ✅ | MD5验证 |
| 订单查询 | ✅ | 使用 merchant_order_no |
| 状态检查 | ✅ | status=1 才处理 |
| 余额更新 | ✅ | 使用 pay_amount |
| 返回格式 | ✅ | 返回小写 "success" |

### ✅ 查询订单 - `/api/pay/query`

| 参数 | 状态 | 说明 |
|------|------|------|
| merchantCode | ✅ | 已配置 |
| merchantOrderNo | ✅ | 已支持 |
| sign | ✅ | MD5签名 |

---

## 🎯 测试建议

### 1. 测试微信支付

```bash
POST /api/payment-v2/wechat
{
  "amount": 100
}

预期返回:
{
  "code": 1,
  "msg": "ok",
  "data": {
    "orderNo": "系统订单号",
    "payUrl": "支付链接",
    "expireTime": 1234567890,
    "amount": 100,
    "status": 0
  }
}
```

### 2. 测试支付宝支付

```bash
POST /api/payment-v2/alipay
{
  "amount": 100
}

预期返回: 同上格式
```

### 3. 测试回调处理

```bash
POST /api/payment-v2/sevenpaynotify
{
  "merchant_code": "M1763172182",
  "order_no": "系统订单号",
  "merchant_order_no": "商户订单号",
  "amount": 100,
  "pay_amount": 100,
  "status": 1,
  "payment_time": 1234567890,
  "sign": "MD5签名"
}

预期返回: success (小写)
```

---

## ✨ 修复完成

✅ **所有问题已修复，代码完全符合 SevenPay 官方文档规范！**

---

## 📝 注意事项

1. **金额范围**:
   - 微信: 100-3000元
   - 支付宝: 100-20000元

2. **回调IP**: 23.94.207.16（已配置）

3. **签名算法**: MD5（小写）

4. **返回格式**: 必须返回小写 `success`

5. **字段命名**:
   - 请求/回调: 下划线命名
   - 响应: 驼峰命名

---

**修复完成！可以开始测试支付流程。** 🚀
