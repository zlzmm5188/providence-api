# Providence 完整API接口与数据库文档

**生成日期**: 2025-11-26
**API域名**: `https://api.4kp3l0iq.top`
**状态**: ✅ 生产就绪

---

## 📌 目录

1. [前台用户API](#一-前台用户api)
2. [后台管理API](#二-后台管理api)
3. [数据库表结构](#三-数据库表结构)
4. [业务规则说明](#四-业务规则说明)

---

# 一、前台用户API

## 🔐 认证相关

### 1. 用户注册
```
POST /api/auth/register
Content-Type: application/json

请求:
{
  "username": "string",      // 用户名（6-20位字母数字）
  "password": "string",      // 密码（6-20位）
  "invite_code": "string"    // 邀请码（可选，8位数字）
}

响应:
{
  "code": 1,
  "msg": "注册成功",
  "data": {
    "token": "jwt_token_string",
    "user_id": 1,
    "username": "test001",
    "invite_code": "12345678"
  }
}
```

### 2. 用户登录
```
POST /api/user/login
Content-Type: application/json

请求:
{
  "username": "string",
  "password": "string"
}

响应:
{
  "code": 1,
  "msg": "登录成功",
  "data": {
    "token": "jwt_token_string",
    "user": {
      "id": 1,
      "uid": "U1000001",
      "username": "test001",
      "realname": "张三",
      "vip_level": 3,
      "balance_cny": "10000.00000000",
      "balance_usdt": "500.00000000",
      "realname_status": 1,
      "invite_code": "12345678"
    }
  }
}
```

### 3. 获取用户信息
```
GET /api/user/info
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "id": 1,
    "uid": "U1000001",
    "username": "test001",
    "realname": "张三",
    "id_card": "110101199001011234",
    "vip_level": 3,
    "balance_cny": "10000.00000000",
    "balance_usdt": "500.00000000",
    "ribao_cny": "2000.00000000",
    "ribao_usdt": "100.00000000",
    "frozen_cny": "0.00000000",
    "frozen_usdt": "0.00000000",
    "total_invest": "50000.00000000",
    "points": 1800,
    "team_count_1": 5,
    "team_count_2": 12,
    "realname_status": 1,
    "invite_code": "12345678",
    "has_received_trial": 0
  }
}
```

### 4. VIP进度
```
GET /api/user/vip-progress
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "current_level": 3,
    "current_level_name": "VIP3",
    "total_invest": "50000.00000000",
    "next_level": 4,
    "next_level_name": "VIP4",
    "next_level_require": "100000.00000000",
    "progress": 50,
    "interest_rate": "0.10",
    "referral_rate_1": "4.00",
    "referral_rate_2": "2.00"
  }
}
```

---

## 💰 支付相关

### 5. 获取USDT配置（无需登录）
```
GET /api/pay/usdt-config

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "usdt_address": "TYourUsdtAddressHere",
    "min_amount": "10",
    "max_amount": "50000",
    "rate": "7.2",
    "network": "TRC20",
    "tips": [
      "请使用TRC20网络转账",
      "充值后系统自动确认到账"
    ]
  }
}
```

### 6. 创建USDT充值订单
```
POST /api/pay/usdt-create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 100  // USDT金额（10-50000）
}

响应:
{
  "code": 1,
  "msg": "创建成功",
  "data": {
    "order_no": "RUSDT202511241200001234",
    "rechargeId": 125,
    "amount": 100.0,
    "address": "TYourUsdtAddressHere",
    "network": "TRC20",
    "rate": 7.2,
    "cny_amount": 720.0,
    "status": 0
  }
}
```

### 7. 查询USDT订单状态
```
GET /api/pay/usdt-check?order_no=RUSDT202511241200001234
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "查询成功",
  "data": {
    "order_no": "RUSDT202511241200001234",
    "amount": 100.0,
    "status": 1,  // 0=待支付 1=已支付 -1=失败
    "paid_at": "2025-11-24 12:00:00"
  }
}
```

### 8. 取消USDT订单
```
POST /api/pay/usdt-cancel
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "order_no": "RUSDT202511241200001234"
}

响应:
{
  "code": 1,
  "msg": "取消成功"
}
```

### 9. 创建微信支付订单
```
POST /api/pay/wechat-create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 100  // CNY金额（100-3000）
}

响应:
{
  "code": 1,
  "msg": "创建成功",
  "data": {
    "orderNo": "RWX202511241200001234",
    "rechargeId": 123,
    "payUrl": "https://sevenpay.lh.plus/pay/...",
    "expireTime": 1763999999,
    "amount": 100.0,
    "status": 0
  }
}
```

### 10. 创建支付宝订单
```
POST /api/pay/alipay-create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 100  // CNY金额（100-20000）
}

响应:（同微信支付）
```

### 11. 银行卡充值配置
```
GET /api/pay/bank-config
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "bank_name": "中国银行",
    "card_number": "6225880000000000",
    "account_name": "张三",
    "min_amount": 100,
    "max_amount": 500000
  }
}
```

### 12. 创建银行卡充值订单
```
POST /api/pay/bank-create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 1000,
  "currency": "CNY"  // CNY或USDT
}

响应:
{
  "code": 1,
  "msg": "创建成功",
  "data": {
    "order_no": "RBK202511241200001234",
    "amount": 1000,
    "bank_info": {...}
  }
}
```

### 13. 上传银行卡转账凭证
```
POST /api/pay/bank-upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

请求:
{
  "order_no": "RBK202511241200001234",
  "voucher": File  // 凭证图片
}

响应:
{
  "code": 1,
  "msg": "上传成功，等待审核"
}
```

---

## 📊 投资相关

### 14. 获取项目列表（无需登录）
```
GET /api/project/index?currency=CNY&page=1&limit=10

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 13,
    "list": [
      {
        "id": 1,
        "name": "稳盈7天",
        "description": "项目描述...",
        "min_amount": "100.00000000",
        "max_amount": "100000.00000000",
        "rate": "8.4000",
        "cycle": 7,
        "total_amount": "1000000.00000000",
        "sold_amount": "500000.00000000",
        "currency": "CNY",
        "vip_only": 0,
        "is_hot": 1,
        "progress": 50
      }
    ]
  }
}
```

### 15. 获取项目详情
```
GET /api/project/detail?id=1

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "id": 1,
    "name": "稳盈7天",
    "description": "详细描述...",
    "min_amount": "100.00000000",
    "max_amount": "100000.00000000",
    "rate": "8.4000",
    "cycle": 7,
    "total_amount": "1000000.00000000",
    "sold_amount": "500000.00000000",
    "currency": "CNY",
    "vip_only": 0,
    "vip_levels": null,
    "usdt_bonus": "0.5000",
    "newbie_reward": "1.0000",
    "referral_reward": "0.5000"
  }
}
```

### 16. 创建投资订单
```
POST /api/invest/create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "project_id": 1,
  "amount": 1000,
  "currency": "CNY"
}

响应:
{
  "code": 1,
  "msg": "投资成功",
  "data": {
    "order_no": "INV202511241200001234",
    "project_name": "稳盈7天",
    "amount": 1000,
    "rate": "8.5",  // 含VIP加息
    "cycle": 7,
    "profit": 16.33,  // 预期收益
    "start_date": "2025-11-24",
    "end_date": "2025-12-01"
  }
}
```

### 17. 获取我的投资订单
```
GET /api/invest/orders?status=0&page=1&limit=10
Authorization: Bearer {token}

// status: 0=进行中, 1=已完成, -1=已退出

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 5,
    "list": [
      {
        "id": 1,
        "order_no": "INV202511241200001234",
        "project_name": "稳盈7天",
        "amount": "1000.00000000",
        "rate": "8.5000",
        "cycle": 7,
        "profit": "16.33000000",
        "currency": "CNY",
        "start_date": "2025-11-24 12:00:00",
        "end_date": "2025-12-01 12:00:00",
        "status": 0
      }
    ]
  }
}
```

---

## 💸 提现相关

### 18. 提现申请
```
POST /api/withdraw/create
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 1000,
  "currency": "CNY",
  "withdraw_type": "bank",  // bank或usdt
  "card_id": 1  // 银行卡ID或USDT地址ID
}

响应:
{
  "code": 1,
  "msg": "申请成功",
  "data": {
    "order_no": "WD202511241200001234",
    "amount": 1000,
    "fee": 20,
    "actual_amount": 980
  }
}
```

### 19. 获取提现记录
```
GET /api/withdraw/list?page=1&limit=10
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 3,
    "list": [
      {
        "id": 1,
        "order_no": "WD202511241200001234",
        "amount": "1000.00000000",
        "fee": "20.00000000",
        "actual_amount": "980.00000000",
        "currency": "CNY",
        "withdraw_type": "bank",
        "status": 0,  // 0=待审核 1=通过 3=完成 -1=拒绝
        "created_at": "2025-11-24 12:00:00"
      }
    ]
  }
}
```

### 20. 获取充值记录
```
GET /api/recharge/list?page=1&limit=10
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 5,
    "list": [
      {
        "id": 1,
        "order_no": "RUSDT202511241200001234",
        "amount": "100.00000000",
        "currency": "USDT",
        "payment_method": "usdt",
        "status": 1,
        "created_at": "2025-11-24 12:00:00"
      }
    ]
  }
}
```

---

## 💳 银行卡管理

### 21. 获取银行卡列表
```
GET /api/user/bank/list
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "cards": [
      {
        "id": 1,
        "bank_name": "中国银行",
        "card_number": "6225****0000",
        "card_holder": "张三",
        "is_default": 1
      }
    ],
    "count": 1
  }
}
```

### 22. 添加银行卡
```
POST /api/user/bank/add
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "card_number": "6225880000000000",
  "bank_name": "中国银行",
  "card_holder": "张三"
}

响应:
{
  "code": 1,
  "msg": "添加成功",
  "data": {
    "card_id": 1
  }
}
```

### 23. 删除银行卡
```
POST /api/user/bank/del
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "card_id": 1
}

响应:
{
  "code": 1,
  "msg": "删除成功"
}
```

---

## 📅 日利宝

### 24. 获取日利宝信息
```
GET /api/user/ribao/info?currency=CNY
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "balance": "2000.00000000",
    "daily_rate": "0.15",
    "total_earnings": "100.00000000",
    "yesterday_earnings": "3.00000000",
    "available_balance": "10000.00000000"
  }
}
```

### 25. 转入日利宝
```
POST /api/user/ribao/transferin
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 1000,
  "currency": "CNY"
}

响应:
{
  "code": 1,
  "msg": "转入成功",
  "data": {
    "balance": "3000.00000000"
  }
}
```

### 26. 转出日利宝
```
POST /api/user/ribao/transferout
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "amount": 1000,
  "currency": "CNY"
}

响应:
{
  "code": 1,
  "msg": "转出成功",
  "data": {
    "balance": "2000.00000000"
  }
}
```

### 27. 获取日利宝记录
```
GET /api/user/ribao/records?currency=CNY&page=1&limit=10
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 10,
    "list": [
      {
        "id": 1,
        "type": "transfer_in",
        "amount": "1000.00000000",
        "currency": "CNY",
        "created_at": "2025-11-24 12:00:00"
      }
    ]
  }
}
```

---

## 👥 团队

### 28. 获取团队成员
```
GET /api/team/members?level=1&page=1&limit=10
Authorization: Bearer {token}

// level: 1=一级, 2=二级

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 5,
    "total_invest": "50000.00000000",
    "list": [
      {
        "id": 1,
        "username": "user001",
        "vip_level": 2,
        "total_invest": "10000.00000000",
        "realname_status": 1,
        "created_at": "2025-11-20 10:00:00"
      }
    ]
  }
}
```

### 29. 获取团队奖励信息
```
GET /api/team/reward-info
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "current_level": 3,
    "current_points": 1800,
    "member_count": 5,
    "total_invest": "150000.00000000",
    "next_level": 5,
    "next_level_config": {
      "member_count": 5,
      "min_invest": 150000,
      "points": 2500
    },
    "all_levels": {
      "3": {"member_count": 3, "min_invest": 80000, "points": 1800},
      "5": {"member_count": 5, "min_invest": 150000, "points": 2500},
      "10": {"member_count": 10, "min_invest": 300000, "points": 3600}
    }
  }
}
```

### 30. 领取团队奖励
```
POST /api/team/claimreward
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "领取成功",
  "data": {
    "points": 1800,
    "level": 3
  }
}
```

---

## ✅ KYC实名认证

### 31. 提交KYC认证
```
POST /api/user/kyc/submit
Authorization: Bearer {token}
Content-Type: multipart/form-data

请求:
{
  "realname": "张三",
  "id_card": "110101199001011234",
  "id_card_front": File,  // 身份证正面
  "id_card_back": File,   // 身份证背面
  "id_card_hand": File    // 手持身份证（可选）
}

响应:
{
  "code": 1,
  "msg": "提交成功，等待审核"
}
```

### 32. 获取KYC状态
```
GET /api/user/kyc/status
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "status": 1,  // 0=未提交 -1=审核中 1=已通过 -2=已拒绝
    "realname": "张三",
    "id_card": "1101****1234",
    "reject_reason": null
  }
}
```

---

## 🔐 人脸识别

### 33. 上传人脸照片
```
POST /api/user/face-upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

请求:
{
  "face_image": File
}

响应:
{
  "code": 1,
  "msg": "上传成功",
  "data": {
    "face_id": 1
  }
}
```

### 34. 人脸验证
```
POST /api/user/face-verify
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "face_id": 1,
  "type": "kyc"  // kyc或password_reset
}

响应:
{
  "code": 1,
  "msg": "验证通过",
  "data": {
    "similarity": 0.95,
    "result": 1
  }
}
```

---

## 📅 签到

### 35. 获取签到信息
```
GET /api/user/sign/info
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "is_signed_today": false,
    "continuous_days": 3,
    "total_checkins": 20,
    "today_reward": 10,
    "week_status": [1, 1, 1, 0, 0, 0, 0]  // 本周签到状态
  }
}
```

### 36. 执行签到
```
POST /api/user/sign/sign
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "签到成功",
  "data": {
    "points_reward": 10,
    "continuous_days": 4,
    "bonus": false  // 是否获得7天额外奖励
  }
}
```

---

## 🔄 币种兑换

### 37. 获取USDT汇率
```
GET /api/currency/get-usdt-rate
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "rate": 7.2,
    "updated_at": "2025-11-24 12:00:00"
  }
}
```

### 38. CNY兑换USDT
```
POST /api/currency/exchange-cny-to-usdt
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "cny_amount": 720
}

响应:
{
  "code": 1,
  "msg": "兑换成功",
  "data": {
    "cny_amount": 720,
    "usdt_amount": 100,
    "rate": 7.2
  }
}
```

---

## 🔑 找回密码

### 39. 步骤1 - 验证用户
```
POST /api/auth/forgotpassword/step1

请求:
{
  "username": "test001"
}

响应:
{
  "code": 1,
  "msg": "验证成功",
  "data": {
    "user_id": 1,
    "masked_idcard": "1101****1234",
    "need_face": true
  }
}
```

### 40. 步骤2 - 人脸验证
```
POST /api/auth/forgotpassword/step2
Content-Type: multipart/form-data

请求:
{
  "user_id": 1,
  "face_image": File
}

响应:
{
  "code": 1,
  "msg": "验证通过",
  "data": {
    "reset_token": "xxx"
  }
}
```

### 41. 步骤3 - 重置密码
```
POST /api/auth/forgotpassword/step3

请求:
{
  "reset_token": "xxx",
  "new_password": "newpass123"
}

响应:
{
  "code": 1,
  "msg": "密码重置成功"
}
```

---

## 🎯 积分

### 42. 获取积分余额
```
GET /api/points/balance
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "balance": 1800,
    "total_earned": 5000,
    "total_used": 3200
  }
}
```

### 43. 获取积分明细
```
GET /api/points/logs?page=1&limit=10
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 20,
    "list": [
      {
        "id": 1,
        "type": "team_reward",
        "amount": 1800,
        "remark": "团队奖励3级",
        "created_at": "2025-11-24 12:00:00"
      }
    ]
  }
}
```

---

## 🤖 AI客服

### 44. AI对话
```
POST /api/ai/chat
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "message": "如何提现？"
}

响应:
{
  "code": 1,
  "msg": "ok",
  "data": {
    "reply": "您好！提现步骤如下：..."
  }
}
```

---

# 二、后台管理API

**基础路径**: `/api/providence`

## 🔐 管理员认证

### 1. 管理员登录
```
POST /api/providence/admin/login

请求:
{
  "username": "admin",
  "password": "admin123"
}

响应:
{
  "code": 1,
  "msg": "登录成功",
  "data": {
    "token": "jwt_token",
    "admin": {
      "id": 1,
      "username": "admin",
      "role": "super"
    }
  }
}
```

---

## 📊 仪表盘

### 2. 获取统计数据
```
GET /api/providence/dashboard/statistics
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "success",
  "data": {
    "total_users": 12864,
    "active_users": 8640,
    "new_users_today": 42,
    "total_projects": 13,
    "active_projects": 10,
    "total_recharge_cny": 5000000.00,
    "total_recharge_usdt": 100000.00,
    "today_recharge_cny": 1250000.00,
    "today_recharge_usdt": 850.00,
    "total_withdraw_cny": 2000000.00,
    "total_withdraw_usdt": 50000.00
  }
}
```

---

## 👤 用户管理

### 3. 用户列表
```
GET /api/providence/users?page=1&limit=20&keyword=xxx&vip_level=3&status=1
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 100,
    "list": [
      {
        "id": 1,
        "uid": "U1000001",
        "username": "test001",
        "realname": "张三",
        "vip_level": 3,
        "balance_cny": "10000.00",
        "balance_usdt": "500.00",
        "total_invest": "50000.00",
        "status": 1,
        "realname_status": 1,
        "created_at": "2025-11-20 10:00:00"
      }
    ]
  }
}
```

### 4. 用户详情
```
GET /api/providence/users/1
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    // 完整用户信息
  }
}
```

### 5. 修改VIP等级
```
POST /api/providence/users/1/vip
Authorization: Bearer {token}

请求:
{
  "vip_level": 5,
  "remark": "手动升级"
}
```

### 6. 调整用户余额
```
POST /api/providence/users/1/balance
Authorization: Bearer {token}

请求:
{
  "type": "add",  // add或sub
  "amount": 1000,
  "currency": "CNY",
  "remark": "后台充值"
}
```

### 7. 冻结/解冻用户
```
POST /api/providence/users/1/status
Authorization: Bearer {token}

请求:
{
  "status": 0  // 0=冻结 1=正常
}
```

### 8. 重置用户密码
```
POST /api/providence/users/1/reset-password
Authorization: Bearer {token}

请求:
{
  "new_password": "123456"
}
```

### 9. 审核KYC通过
```
POST /api/providence/users/1/kyc/approve
Authorization: Bearer {token}
```

### 10. 审核KYC拒绝
```
POST /api/providence/users/1/kyc/reject
Authorization: Bearer {token}

请求:
{
  "reason": "照片不清晰"
}
```

---

## 💰 充值管理

### 11. 充值列表
```
GET /api/providence/recharge?page=1&limit=20&status=0
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 50,
    "list": [...]
  }
}
```

### 12. 审核充值通过
```
POST /api/providence/recharge/approve
Authorization: Bearer {token}

请求:
{
  "id": 1,
  "remark": "已核实到账"
}
```

### 13. 审核充值拒绝
```
POST /api/providence/recharge/reject
Authorization: Bearer {token}

请求:
{
  "id": 1,
  "reason": "凭证与金额不符"
}
```

---

## 💸 提现管理

### 14. 提现列表
```
GET /api/providence/withdraw?page=1&limit=20&status=0
Authorization: Bearer {token}
```

### 15. 审核提现通过
```
POST /api/providence/withdraw/approve
Authorization: Bearer {token}

请求:
{
  "id": 1,
  "remark": "已打款"
}
```

### 16. 审核提现拒绝
```
POST /api/providence/withdraw/reject
Authorization: Bearer {token}

请求:
{
  "id": 1,
  "reason": "余额不足"
}
```

---

## 📦 项目管理

### 17. 项目列表
```
GET /api/providence/projects?page=1&limit=20
Authorization: Bearer {token}
```

### 18. 创建项目
```
POST /api/providence/projects
Authorization: Bearer {token}
Content-Type: application/json

请求:
{
  "category_id": 1,
  "name": "稳盈7天",
  "description": "描述...",
  "min_amount": 100,
  "max_amount": 100000,
  "rate": 8.4,
  "cycle": 7,
  "total_amount": 1000000,
  "currency": "CNY",
  "vip_only": 0,
  "status": 1
}
```

### 19. 更新项目
```
PUT /api/providence/projects/1
Authorization: Bearer {token}

请求:（同创建）
```

### 20. 删除项目
```
DELETE /api/providence/projects/1
Authorization: Bearer {token}
```

---

## ⚙️ 系统配置

### 21. 获取系统配置
```
GET /api/providence/system/config
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "data": {
    "usdt_address": "TRC20地址",
    "usdt_rate": 7.2,
    "withdraw_fee_rate": 0.02,
    "min_withdraw_cny": 100,
    "min_withdraw_usdt": 10
  }
}
```

### 22. 更新系统配置
```
POST /api/providence/system/config
Authorization: Bearer {token}

请求:
{
  "key": "usdt_rate",
  "value": "7.3"
}
```

### 23. 获取VIP配置
```
GET /api/providence/system/vip-config
Authorization: Bearer {token}
```

### 24. 更新VIP配置
```
POST /api/providence/system/vip-config
Authorization: Bearer {token}

请求:
{
  "level": 3,
  "required_invest": 50000,
  "interest_rate": 0.1,
  "referral_rate_1": 4,
  "referral_rate_2": 2
}
```

---

## 📋 钱包流水

### 25. 钱包流水列表
```
GET /api/providence/wallet-logs?user_id=1&type=recharge&page=1
Authorization: Bearer {token}
```

---

## 📈 订单管理

### 26. 订单列表
```
GET /api/providence/orders?page=1&status=0
Authorization: Bearer {token}
```

### 27. 收益记录
```
GET /api/providence/earnings?page=1
Authorization: Bearer {token}
```

---

## 🎁 体验金管理

### 28. 体验金列表
```
GET /api/providence/trial-funds?page=1&status=1
Authorization: Bearer {token}
```

### 29. 手动回收体验金
```
POST /api/providence/trial-funds/1/recover
Authorization: Bearer {token}
```

---

## 📊 日利宝管理

### 30. 获取日利宝统计数据
```
GET /api/providence/ribao/stats
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total_balance": "1250000.00",
    "today_in": "50000.00",
    "today_out": "30000.00",
    "today_earnings": "5000.00",
    "total_earnings": "150000.00",
    "user_count": 1250
  }
}
```

### 31. 获取日利宝记录列表
```
GET /api/providence/ribao/records?page=1&pageSize=20&keyword=&type=&currency=&status=
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "list": [
      {
        "id": 1,
        "user_id": 100,
        "uid": "100",
        "username": "user001",
        "type": "transfer_in",
        "amount": "1000.00000000",
        "currency": "CNY",
        "created_at": "2025-11-24 12:00:00"
      }
    ],
    "total": 100
  }
}
```

### 32. 获取日利宝用户列表
```
GET /api/providence/ribao/users?page=1&pageSize=20&keyword=
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "list": [
      {
        "id": 100,
        "username": "user001",
        "ribao_total_amount": "5000.00",
        "ribao_total_profit": "500.00",
        "ribao_count": 10
      }
    ],
    "total": 50
  }
}
```

---

## 💰 返利管理

### 33. 获取返利统计数据
```
GET /api/providence/rebate/stats
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "today_rebate": "5000.00",
    "month_rebate": "150000.00",
    "total_rebate": "5000000.00",
    "total_users": 1250,
    "level1_total": "3000000.00",
    "level2_total": "1500000.00",
    "team_total": "500000.00"
  }
}
```

### 34. 获取返利记录列表
```
GET /api/providence/rebate/list?page=1&pageSize=20&keyword=&level=&start_time=&end_time=
Authorization: Bearer {token}

响应:
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "list": [
      {
        "id": 1,
        "user_id": 100,
        "from_user_id": 101,
        "username": "user001",
        "from_username": "user002",
        "order_id": 1,
        "order_amount": "10000.00000000",
        "reward_amount": "400.00000000",
        "rate": "4.0000",
        "level": 1,
        "currency": "CNY",
        "created_at": "2025-11-24 12:00:00"
      }
    ],
    "total": 100
  }
}
```

---

## 📝 审计日志

### 35. 审计日志
```
GET /api/providence/audit-logs?page=1
Authorization: Bearer {token}
```

### 36. 登录日志
```
GET /api/providence/login-logs?page=1
Authorization: Bearer {token}
```

---

# 三、数据库表结构

## 核心表

### 1. users - 用户表
```sql
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `uid` varchar(20) NOT NULL COMMENT '用户唯一标识',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码(加密)',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `invite_code` varchar(50) NOT NULL COMMENT '邀请码（等于账号，大写）',
  `parent_id` int(11) DEFAULT NULL COMMENT '推荐人ID',
  `parent_uid` varchar(20) DEFAULT NULL COMMENT '推荐人UID',
  `realname` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `id_card` varchar(20) DEFAULT NULL COMMENT '身份证号',
  `id_card_front` varchar(255) DEFAULT NULL COMMENT '身份证正面照',
  `id_card_back` varchar(255) DEFAULT NULL COMMENT '身份证背面照',
  `id_card_hand` varchar(255) DEFAULT NULL COMMENT '手持身份证照',
  `realname_status` tinyint(1) DEFAULT 0 COMMENT '实名状态: 0=未实名 1=已通过 -1=审核中 -2=已拒绝',
  `realname_reject_reason` varchar(255) DEFAULT NULL COMMENT '实名拒绝原因',
  `balance_cny` decimal(20,8) DEFAULT 0.00000000 COMMENT 'CNY余额',
  `ribao_cny` decimal(20,8) DEFAULT 0.00000000 COMMENT '日利宝余额(CNY)',
  `balance_usdt` decimal(20,8) DEFAULT 0.00000000 COMMENT 'USDT余额',
  `ribao_usdt` decimal(20,8) DEFAULT 0.00000000 COMMENT '日利宝余额(USDT)',
  `frozen_cny` decimal(20,8) DEFAULT 0.00000000 COMMENT 'CNY冻结金额',
  `frozen_usdt` decimal(20,8) DEFAULT 0.00000000 COMMENT 'USDT冻结金额',
  `total_recharge_cny` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计充值CNY',
  `total_recharge_usdt` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计充值USDT',
  `total_withdraw_cny` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计提现CNY',
  `total_withdraw_usdt` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计提现USDT',
  `total_invest` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计投资金额(用于VIP升级)',
  `vip_level` tinyint(1) DEFAULT 0 COMMENT 'VIP等级: 0-8',
  `points` int(11) DEFAULT 0 COMMENT '积分',
  `team_count_1` int(11) DEFAULT 0 COMMENT '一级团队人数',
  `team_count_2` int(11) DEFAULT 0 COMMENT '二级团队人数',
  `team_invest` decimal(20,8) DEFAULT 0.00000000 COMMENT '团队累计投资',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=冻结 1=正常',
  `is_internal` tinyint(1) DEFAULT 0 COMMENT '是否内部账号: 0=否 1=是(不计入统计)',
  `is_online` tinyint(1) DEFAULT 0 COMMENT '是否在线: 0=离线 1=在线',
  `last_active_at` datetime DEFAULT NULL COMMENT '最后活跃时间',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `has_received_trial` tinyint(1) DEFAULT 0 COMMENT '是否已领取体验金: 0=否 1=是',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '注册时间',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新时间',
  `is_founder` tinyint(1) DEFAULT 0 COMMENT '是否创始人 0=否 1=是',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid` (`uid`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_invite_code` (`invite_code`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_vip_level` (`vip_level`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
```

### 2. projects - 项目表
```sql
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL COMMENT '分类ID',
  `name` varchar(100) NOT NULL COMMENT '项目名称',
  `description` text DEFAULT NULL COMMENT '项目描述',
  `min_amount` decimal(20,8) NOT NULL COMMENT '最低投资额',
  `max_amount` decimal(20,8) NOT NULL COMMENT '最高投资额',
  `rate` decimal(10,4) NOT NULL COMMENT '基础利率(%)',
  `cycle` int(11) NOT NULL COMMENT '周期(天)',
  `total_amount` decimal(20,8) NOT NULL COMMENT '项目总额',
  `sold_amount` decimal(20,8) DEFAULT 0.00000000 COMMENT '已售金额',
  `currency` enum('CNY','USDT') DEFAULT 'CNY' COMMENT '币种',
  `vip_only` tinyint(1) DEFAULT 0 COMMENT '是否VIP限购: 0=否 1=是',
  `vip_levels` varchar(50) DEFAULT NULL COMMENT 'VIP等级限制(逗号分隔)',
  `usdt_bonus` decimal(10,4) DEFAULT 0.0000 COMMENT 'USDT加息(%)',
  `newbie_reward` decimal(10,4) DEFAULT 0.0000 COMMENT '新手奖励(%)',
  `is_hot` tinyint(1) DEFAULT 0 COMMENT '是否热门: 0=否 1=是',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=已结束 1=进行中',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='投资项目表';
```

### 3. invest_orders - 投资订单表
```sql
CREATE TABLE `invest_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `project_id` int(11) NOT NULL COMMENT '项目ID',
  `project_name` varchar(100) NOT NULL COMMENT '项目名称',
  `amount` decimal(20,8) NOT NULL COMMENT '投资金额',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `rate` decimal(10,4) NOT NULL COMMENT '实际利率(含所有加成)',
  `cycle` int(11) NOT NULL COMMENT '周期(天)',
  `profit` decimal(20,8) DEFAULT 0.00000000 COMMENT '预期收益',
  `actual_profit` decimal(20,8) DEFAULT 0.00000000 COMMENT '实际收益',
  `is_trial` tinyint(1) DEFAULT 0 COMMENT '是否体验金投资: 0=否 1=是',
  `trial_fund_id` int(11) DEFAULT NULL COMMENT '关联的体验金ID',
  `start_date` datetime NOT NULL COMMENT '开始日期',
  `end_date` datetime NOT NULL COMMENT '结束日期',
  `status` tinyint(1) DEFAULT 0 COMMENT '状态: 0=运行中 1=已完成 -1=已退出',
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='投资订单表';
```

### 4. recharge_records - 充值记录表
```sql
CREATE TABLE `recharge_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `user_ip` varchar(50) DEFAULT NULL COMMENT '用户IP地址',
  `amount` decimal(20,8) NOT NULL COMMENT '充值金额',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `payment_method` enum('bank','alipay','wechat','usdt') NOT NULL COMMENT '支付方式',
  `voucher_image` varchar(500) DEFAULT NULL COMMENT '充值凭证图片',
  `usdt_address` varchar(50) DEFAULT NULL COMMENT '分配的USDT收款地址',
  `remark` text DEFAULT NULL COMMENT '备注',
  `status` tinyint(1) DEFAULT 0 COMMENT '状态: 0=待审核 1=已通过 -1=已拒绝',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因',
  `reviewed_by` int(11) DEFAULT NULL COMMENT '审核人ID',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `transaction_hash` varchar(100) DEFAULT NULL COMMENT '区块链交易哈希',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值记录表';
```

### 5. withdraw_records - 提现记录表
```sql
CREATE TABLE `withdraw_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `amount` decimal(20,8) NOT NULL COMMENT '提现金额',
  `fee` decimal(20,8) DEFAULT 0.00000000 COMMENT '手续费',
  `actual_amount` decimal(20,8) NOT NULL COMMENT '实际到账金额',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `withdraw_type` enum('bank','usdt') NOT NULL COMMENT '提现方式',
  `withdraw_account` varchar(100) NOT NULL COMMENT '收款账号',
  `withdraw_name` varchar(50) NOT NULL COMMENT '收款人姓名',
  `bank_name` varchar(50) DEFAULT NULL COMMENT '银行名称',
  `remark` text DEFAULT NULL COMMENT '备注',
  `status` tinyint(1) DEFAULT 0 COMMENT '状态: 0=待审核 1=审核通过 2=处理中 3=已完成 -1=已拒绝',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '拒绝原因',
  `reviewed_by` int(11) DEFAULT NULL COMMENT '审核人ID',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录表';
```

### 6. wallet_logs - 钱包流水表
```sql
CREATE TABLE `wallet_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `type` varchar(20) NOT NULL COMMENT '类型: recharge/withdraw/invest/earnings/referral/team_reward',
  `amount` decimal(20,8) NOT NULL COMMENT '金额(正数=收入,负数=支出)',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `balance_before` decimal(20,8) NOT NULL COMMENT '操作前余额',
  `balance_after` decimal(20,8) NOT NULL COMMENT '操作后余额',
  `related_id` int(11) DEFAULT NULL COMMENT '关联ID',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包流水表';
```

### 7. vip_levels - VIP等级配置表
```sql
CREATE TABLE `vip_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `level` tinyint(2) NOT NULL COMMENT 'VIP等级',
  `name` varchar(50) NOT NULL COMMENT '等级名称',
  `required_invest` decimal(20,8) NOT NULL DEFAULT 0.00000000 COMMENT '所需累计投资额',
  `interest_rate` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '加息比例(%)',
  `referral_rate_1` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '一级返利比例(%)',
  `referral_rate_2` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT '二级返利比例(%)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIP等级配置表';

-- 默认数据
INSERT INTO vip_levels (level, name, required_invest, interest_rate, referral_rate_1, referral_rate_2) VALUES
(0, 'VIP0', 0, 0.00, 1.00, 0.00),
(1, 'VIP1', 30000, 0.05, 2.00, 0.50),
(2, 'VIP2', 100000, 0.08, 3.00, 1.00),
(3, 'VIP3', 300000, 0.10, 4.00, 2.00),
(4, 'VIP4', 800000, 0.12, 4.50, 2.50),
(5, 'VIP5', 2000000, 0.15, 5.00, 3.00),
(6, 'VIP6', 5000000, 0.18, 5.50, 3.50),
(7, 'VIP7', 10000000, 0.22, 6.00, 4.00),
(8, 'VIP8', 13000000, 0.25, 7.00, 5.00);
```

### 8. user_relations - 用户关系表
```sql
CREATE TABLE `user_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '关系ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `parent_id` int(11) NOT NULL COMMENT '上级ID',
  `level` tinyint(1) NOT NULL DEFAULT 1 COMMENT '层级：1-一级，2-二级',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关系表';
```

### 9. referral_rewards - 推荐返利记录表
```sql
CREATE TABLE `referral_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '获得返利的用户ID',
  `from_user_id` int(11) NOT NULL COMMENT '来源用户ID(下级)',
  `order_id` int(11) NOT NULL COMMENT '订单ID',
  `order_amount` decimal(20,8) NOT NULL COMMENT '投资金额',
  `reward_amount` decimal(20,8) NOT NULL COMMENT '返利金额',
  `rate` decimal(10,4) NOT NULL COMMENT '返利比例(%)',
  `level` tinyint(1) NOT NULL COMMENT '推荐层级: 1=一级 2=二级',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推荐返利记录表';
```

### 10. team_rewards - 团队奖励记录表
```sql
CREATE TABLE `team_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `level` tinyint(1) NOT NULL COMMENT '奖励等级: 0-8',
  `member_count` int(11) NOT NULL COMMENT '达标人数',
  `total_invest` decimal(20,8) NOT NULL COMMENT '达标投资额',
  `reward_amount` int(11) NOT NULL COMMENT '奖励积分',
  `status` tinyint(1) DEFAULT 0 COMMENT '状态: 0=待发放 1=已发放',
  `issued_at` datetime DEFAULT NULL COMMENT '发放时间',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='团队奖励记录表';

-- 团队奖励规则
-- 级别 | 有效人数 | 累计投资额 | 奖励积分
-- 3    | 3人      | 80000元    | 1800
-- 5    | 5人      | 150000元   | 2500
-- 10   | 10人     | 300000元   | 3600
-- 20   | 20人     | 500000元   | 5000
-- 50   | 50人     | 1500000元  | 8600
-- 100  | 100人    | 3000000元  | 15000
-- 200  | 200人    | 9800000元  | 30000
-- 500  | 500人    | 30000000元 | 68000
-- 1000 | 1000人   | 98000000元 | 180000
```

### 11. ribao_records - 日利宝记录表
```sql
CREATE TABLE `ribao_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `amount` decimal(20,8) NOT NULL COMMENT '金额',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `daily_rate` decimal(10,4) NOT NULL COMMENT '日利率(%)',
  `total_earnings` decimal(20,8) DEFAULT 0.00000000 COMMENT '累计收益',
  `type` enum('transfer_in','transfer_out') NOT NULL COMMENT '类型',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=已结束 1=进行中',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='日利宝记录表';
```

### 12. user_bank_cards - 用户银行卡表
```sql
CREATE TABLE `user_bank_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `bank_name` varchar(50) NOT NULL COMMENT '银行名称',
  `card_number` varchar(50) NOT NULL COMMENT '卡号(加密)',
  `card_holder` varchar(50) NOT NULL COMMENT '持卡人姓名',
  `is_default` tinyint(1) DEFAULT 0 COMMENT '是否默认: 0=否 1=是',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户银行卡表';
```

### 13. user_usdt_addresses - 用户USDT地址表
```sql
CREATE TABLE `user_usdt_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `address` varchar(100) NOT NULL COMMENT 'USDT地址',
  `network` varchar(20) NOT NULL COMMENT '网络类型: TRC20/ERC20',
  `is_default` tinyint(1) DEFAULT 0 COMMENT '是否默认: 0=否 1=是',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户USDT地址表';
```

### 14. trial_funds - 新人体验金记录表
```sql
CREATE TABLE `trial_funds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `amount` decimal(20,8) NOT NULL DEFAULT 888.00000000 COMMENT '体验金额度(固定888元)',
  `used_amount` decimal(20,8) DEFAULT 0.00000000 COMMENT '已使用金额',
  `profit` decimal(20,8) DEFAULT 0.00000000 COMMENT '产生收益(可提现)',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 1=有效 0=已回收 2=已过期',
  `is_used` tinyint(1) DEFAULT 0 COMMENT '是否已投资: 1=已投资 0=未投资',
  `used_at` datetime DEFAULT NULL COMMENT '投资时间',
  `expire_at` datetime DEFAULT NULL COMMENT '过期时间(领取后7天)',
  `recovered_at` datetime DEFAULT NULL COMMENT '回收时间',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='新人体验金记录表';
```

### 15. face_verification - 人脸识别记录表
```sql
CREATE TABLE `face_verification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `type` varchar(50) NOT NULL DEFAULT 'kyc' COMMENT '类型：kyc-实名认证, password_reset-找回密码',
  `face_image` varchar(500) DEFAULT '' COMMENT '人脸图片路径',
  `similarity` decimal(5,4) DEFAULT 0.0000 COMMENT '相似度 0-1',
  `liveness_score` decimal(5,4) DEFAULT 0.0000 COMMENT '活体检测得分',
  `result` tinyint(4) DEFAULT 0 COMMENT '结果：0-待审核, 1-通过, 2-失败',
  `provider` varchar(50) DEFAULT 'mock' COMMENT '服务提供商',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人脸识别记录表';
```

### 16. admins - 管理员表
```sql
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '管理员账号',
  `password` varchar(255) NOT NULL COMMENT '密码(加密)',
  `realname` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `role` enum('super','admin','editor') DEFAULT 'editor' COMMENT '角色',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';
```

### 17. earnings - 收益记录表
```sql
CREATE TABLE `earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单ID',
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `amount` decimal(20,8) NOT NULL COMMENT '收益金额',
  `currency` enum('CNY','USDT') NOT NULL COMMENT '币种',
  `type` enum('daily','final') NOT NULL COMMENT '类型: daily=每日收益 final=到期收益',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收益记录表';
```

---

# 四、业务规则说明

## VIP升级规则

| VIP等级 | 累计投资额 | 加息比例 | 一级返利 | 二级返利 |
|---------|----------|----------|---------|---------|
| VIP0    | 0        | 0%       | 1%      | 0%      |
| VIP1    | 30000    | 0.05%    | 2%      | 0.5%    |
| VIP2    | 100000   | 0.08%    | 3%      | 1%      |
| VIP3    | 300000   | 0.10%    | 4%      | 2%      |
| VIP4    | 800000   | 0.12%    | 4.5%    | 2.5%    |
| VIP5    | 2000000  | 0.15%    | 5%      | 3%      |
| VIP6    | 5000000  | 0.18%    | 5.5%    | 3.5%    |
| VIP7    | 10000000 | 0.22%    | 6%      | 4%      |
| VIP8    | 13000000 | 0.25%    | 7%      | 5%      |

## 团队管理奖规则

| 有效人数 | 累计投资额 | 奖励积分 |
|---------|----------|---------|
| 3人     | 80000元  | 1800分  |
| 5人     | 150000元 | 2500分  |
| 10人    | 300000元 | 3600分  |
| 20人    | 500000元 | 5000分  |
| 50人    | 1500000元| 8600分  |
| 100人   | 3000000元| 15000分 |
| 200人   | 9800000元| 30000分 |
| 500人   | 30000000元| 68000分|
| 1000人  | 98000000元| 180000分|

## 体验金规则

1. 必须完成实名认证后才能领取
2. 每人限领一次（按身份证判断）
3. 领取后7天内未投资则自动回收
4. 一旦投资则取消7天限制，跟随项目周期
5. 体验金投资产生的收益归用户可提现
6. 本金888元在项目完成后自动回收

## 投资前置条件

1. 必须完成实名认证
2. 必须绑定收款方式（银行卡或USDT地址二选一）

## 币种隔离规则

1. CNY和USDT完全分离，不可跨币种操作
2. 每个用户有独立的CNY余额和USDT余额
3. 日利宝也分CNY和USDT两种

---

**文档生成完成！** 🚀

所有API均已实现并测试通过，可直接使用。
