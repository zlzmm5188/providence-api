# Providence 后台管理 API 接口文档

## 基础信息
- **Base URL**: `https://api.4kp3l0iq.top/api`
- **认证方式**: JWT Token (Bearer Token)
- **Token位置**: Header `Authorization: Bearer {token}`

---

## 一、管理员认证接口

### 1. 管理员登录
**接口**: `POST /api/admin/login`

**请求参数**:
```json
{
  "username": "管理员用户名",
  "password": "密码"
}
```

**响应示例**:
```json
{
  "code": 1,
  "message": "ok",
  "data": {
    "accessToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refreshToken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "id": 1,
    "username": "G138688",
    "realName": "创始人",
    "roles": ["admin"]
  }
}
```

---

### 2. 获取管理员信息
**接口**: `POST /api/admin/getUserInfo`

**请求头**:
```
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "code": 1,
  "message": "ok",
  "data": {
    "id": 1,
    "username": "G138688",
    "realName": "创始人",
    "avatar": "https://api.dicebear.com/7.x/miniavs/svg?seed=1",
    "roles": [{
      "id": "admin",
      "code": "admin",
      "name": "管理员",
      "status": 1
    }],
    "permissions": ["*:*:*"]
  }
}
```

---

### 3. 获取权限代码
**接口**: `POST /api/admin/getPermissions`

**请求头**:
```
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "code": 0,
  "message": "ok",
  "result": ["*:*:*"]
}
```

---

### 4. 刷新Token
**接口**: `POST /api/admin/refreshToken`

**请求头**:
```
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "code": 0,
  "message": "ok",
  "result": {
    "accessToken": "新token",
    "refreshToken": "新token"
  }
}
```

---

### 5. 登出
**接口**: `POST /api/admin/logout`

**请求头**:
```
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "code": 0,
  "message": "ok",
  "result": null
}
```

---

## 二、Bot管理接口（AI运营助理）

### 1. 发布项目
**接口**: `POST /api/bot/admin/project/publish`

**请求头**:
```
Admin-Token: providence_admin_2025
```

**请求参数**:
```json
{
  "name": "项目名称",
  "rate": 0.3,
  "cycle": 7,
  "min_amount": 1000,
  "max_amount": 50000,
  "currency": "CNY",
  "description": "项目描述"
}
```

**响应示例**:
```json
{
  "code": 1,
  "msg": "项目发布成功",
  "data": {
    "id": 1
  }
}
```

---

### 2. 获取财务统计
**接口**: `GET /api/bot/admin/finance/stats`

**请求头**:
```
Admin-Token: providence_admin_2025
```

**响应示例**:
```json
{
  "code": 1,
  "data": {
    "yesterday_recharge": 125000.00,
    "today_recharge": 85000.00,
    "today_withdraw": 50000.00,
    "tomorrow_expire": 200000.00,
    "available_funds": 35000.00,
    "net_cash_flow": -165000.00,
    "hot_projects": [...]
  }
}
```

---

## 三、通用用户接口（管理员也可使用）

管理员登录后，可以使用所有用户接口（通过JWT中的user_id字段）。

### 常用接口列表：

1. **用户信息**: `GET /api/user/info`
2. **项目列表**: `GET /api/project/index`
3. **项目详情**: `GET /api/project/detail`
4. **投资订单**: `GET /api/invest/orders`
5. **团队成员**: `GET /api/team/members`
6. **充值记录**: `GET /api/recharge/list`
7. **提现记录**: `GET /api/withdraw/list`

---

## 四、数据统计接口

### 仪表盘统计（Providence模块）
**接口**: `GET /providence/admin/dashboard/statistics`

**响应示例**:
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "today_recharge": 1250000,
    "today_withdraw": 850000,
    "today_new_users": 42,
    "total_users": 12864,
    "total_active_users": 8640,
    "system_balance_cny": 125000000,
    "system_balance_usdt": 8500,
    "total_invest": 450000000
  }
}
```

---

## 五、错误码说明

| 错误码 | 说明 |
|--------|------|
| 1 | 成功 |
| 0 | 失败 |
| 401 | 未授权/Token无效 |

---

## 六、注意事项

1. **Token有效期**: 2小时（7200秒）
2. **权限**: 管理员拥有所有权限（`*:*:*`）
3. **Bot接口**: 使用特殊的 `Admin-Token` 认证，不是JWT
4. **字段映射**: 数据库字段使用 `start_date`/`end_date`

---

## 七、定时任务

### 自动结算到期订单
**脚本**: `/www/wwwroot/api.4kp3l0iq.top/cron/settle-invest-orders.php`
**执行频率**: 每10分钟
**功能**:
- 自动查找到期订单（status=1, end_date <= now）
- 自动结算：发放本金+收益
- 自动发放推荐返利

**Crontab配置**:
```bash
*/10 * * * * /usr/bin/php /www/wwwroot/api.4kp3l0iq.top/cron/settle-invest-orders.php
```

---

**文档生成时间**: 2025-11-26
**API版本**: v1.0
