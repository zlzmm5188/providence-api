# 🔍 SevenPay 支付对接检查报告

## 文档链接：https://sevenpay.lh.plus/merchants/#/api-document

---

## 📋 官方文档规范

### 1️⃣ 发起支付 API
**请求地址**：`/api/pay/create` (POST)
**Content-Type**：`application/x-www-form-urlencoded`

#### 请求参数：
| 参数名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| merchantCode | String | Y | 商户号 |
| channelType | String | Y | 通道类型 |
| merchantOrderNo | String | Y | 商户订单号（1-64位） |
| amount | Float | Y | 金额 |
| notifyUrl | String | Y | 通知地址 |
| returnUrl | String | Y | **跳转地址** ⚠️ |
| ip | String | Y | 用户IP（别填写服务器IP） |
| title | String | Y | 订单标题 |
| describe | String | Y | 订单描述 |
| extraParam | String | N | 扩展参数 |
| sign | String | Y | MD5签名 |

#### 响应参数：
```json
{
  "code": 1,           // 1=成功, 0=失败
  "message": "...",
  "data": {
    "payUrl": "...",         // ⚠️ 支付链接（不是 pay_url）
    "orderNo": "...",        // ⚠️ 系统订单号（不是 order_no）
    "expireTime": 1234567890 // 10位时间戳
  }
}
```

---

### 2️⃣ 回调通知
**返回值**：收到通知后返回小写 `success`
**只通知一次**：重新回调需到商户后台操作

#### 回调参数（POST到notifyUrl）：
| 参数名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| merchant_code | String | Y | 商户号 |
| order_no | String | Y | 系统订单号 |
| merchant_order_no | String | Y | 商户订单号 |
| amount | Float | Y | 金额 |
| pay_amount | Float | Y | 支付金额 |
| status | Integer | Y | 1=成功, 2=失败 |
| payment_time | Integer | Y | 支付时间(10位时间戳) |
| sign | String | Y | MD5签名 |

#### returnUrl跳转参数：
同回调参数相同

---

### 3️⃣ 查询订单 API
**请求地址**：`/api/pay/query` (POST)

#### 请求参数：
| 参数名 | 类型 | 必填 | 说明 |
|-------|------|------|------|
| merchantCode | String | Y | 商户号 |
| merchantOrderNo | String | Y | 商户订单号 |
| sign | String | Y | MD5签名 |

#### 响应参数：
```json
{
  "code": 1,           // 1=成功, 0=失败
  "message": "...",
  "data": {
    "order_no": "...",        // 系统订单号
    "amount": 0.0,
    "pay_amount": 0.0,
    "ip": "...",
    "title": "...",
    "describe": "...",
    "expire_time": 1234567890,
    "status": 1,              // 0=支付中, 1=成功, 2=失败
    "payment_time": 1234567890,
    "create_time": 1234567890
  }
}
```

---

## ✅ 后端代码对接检查

### 现状检查 (SevenPayService.php)

#### ⚠️ 问题 1：响应字段名不符合文档
**文档要求**：`payUrl`、`orderNo`、`expireTime`
**代码实际**：`pay_url`、`qr_code`

```php
// 现在的代码（第39-41行）
'pay_url' => $result['data']['pay_url'] ?? $result['data']['qrcode'] ?? '',
'qr_code' => $result['data']['qrcode'] ?? ''

// 应该改为
'payUrl' => $result['data']['payUrl'] ?? '',
'orderNo' => $result['data']['orderNo'] ?? '',
'expireTime' => $result['data']['expireTime'] ?? 0
```

#### ⚠️ 问题 2：请求地址错误
**文档要求**：`https://sevenpay.lh.plus/api/pay/create`
**代码实际**：`https://sevenpay.lh.plus/index.php/pay/create` ❌

```php
// 第20行
'payGateway' => 'https://sevenpay.lh.plus/index.php/pay/create',  // ❌ 错误

// 应该改为
'payGateway' => 'https://sevenpay.lh.plus/api/pay/create',  // ✅ 正确
```

#### ⚠️ 问题 3：查询地址错误
**文档要求**：`https://sevenpay.lh.plus/api/pay/query`
**代码实际**：`https://sevenpay.lh.plus/index.php/pay/query` ❌

```php
// 第21行
'queryGateway' => 'https://sevenpay.lh.plus/index.php/pay/query',  // ❌ 错误

// 应该改为
'queryGateway' => 'https://sevenpay.lh.plus/api/pay/query',  // ✅ 正确
```

#### ⚠️ 问题 4：Content-Type错误
**文档要求**：`application/x-www-form-urlencoded`
**代码实际**：可能使用了 JSON

需要检查 `httpPost()` 方法是否使用了正确的 Content-Type

---

## 🔧 修复方案

### 修改 1：SevenPayService.php 第15-27行
```php
private static $config = [
    'merchantId' => 'M1763172182',
    'username' => 'XY8888',
    'merchantName' => 'XY',
    'apiKey' => '3e4cbfb35a35787ccc98bd13027b4a02',
    'payGateway' => 'https://sevenpay.lh.plus/api/pay/create',    // ✅ 修改
    'queryGateway' => 'https://sevenpay.lh.plus/api/pay/query',   // ✅ 修改
    'callbackIP' => '23.94.207.16',
    'channels' => [
        'wechat' => ['code' => 'S02', 'name' => '微信扫码', 'min' => 100, 'max' => 3000],
        'alipay' => ['code' => 'S01', 'name' => '支付宝小额原生', 'min' => 100, 'max' => 20000]
    ]
];
```

### 修改 2：PaymentV2.php 返回字段名
```php
if ($result && isset($result['code']) && $result['code'] == 1) {
    return json([
        'code' => 1,
        'msg' => 'ok',
        'data' => [
            'orderNo' => $result['data']['orderNo'] ?? '',      // ✅ 改为 orderNo
            'payUrl' => $result['data']['payUrl'] ?? '',        // ✅ 改为 payUrl
            'expireTime' => $result['data']['expireTime'] ?? 0, // ✅ 改为 expireTime
            'amount' => (float)$amount,
            'status' => 0
        ]
    ]);
}
```

---

## 📝 前端对应修改 (recharge.html)

```javascript
// 第2038-2042行 需要更新
if (result.data.payUrl) {              // ✅ 改为 payUrl
    window.location.href = result.data.payUrl;
} else if (result.data.qrcode) {       // ✅ 改为从 qrcode 的二维码图片
    const qrCode = result.data.qrcode;
    showQRCodeModal(qrCode, result.data.orderNo, amount, 30 * 60);
}
```

---

## 📊 检查清单

- [ ] 修改 SevenPayService.php 中的 payGateway 和 queryGateway URL
- [ ] 修改 SevenPayService.php 中的响应字段映射
- [ ] 修改 PaymentV2.php 中的返回字段名
- [ ] 修改 recharge.html 中的前端字段适配
- [ ] 验证 httpPost() 方法使用正确的 Content-Type
- [ ] 验证 MD5 签名方法符合文档规范
- [ ] 测试微信支付流程
- [ ] 测试支付宝流程
- [ ] 测试回调通知验证

---

## 🎯 修复优先级

**高优先级**（必须修复）：
1. ✅ payGateway URL 错误
2. ✅ queryGateway URL 错误
3. ✅ 响应字段名不符合文档

**中优先级**（建议修复）：
1. ⚠️ Content-Type 验证
2. ⚠️ MD5 签名验证

**说明**：目前代码虽然有这些问题，但由于前台已经做了容错处理（同时接收 `pay_url` 和 `payUrl` 等），所以功能可能仍然工作。但为了完全符合官方文档规范，应该进行这些修复。
