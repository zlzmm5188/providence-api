# Providence 后台管理系统路由索引

**生成日期**: 2025-11-26
**API基础路径**: `https://api.4kp3l0iq.top/api/providence`
**框架**: ThinkPHP 6.0 多应用模式

---

## 📋 目录

1. [管理员认证](#管理员认证)
2. [仪表盘](#仪表盘)
3. [用户管理](#用户管理)
4. [充值管理](#充值管理)
5. [提现管理](#提现管理)
6. [项目管理](#项目管理)
7. [订单管理](#订单管理)
8. [收益记录](#收益记录)
9. [日利宝管理](#日利宝管理)
10. [返利管理](#返利管理)
11. [系统配置](#系统配置)
12. [体验金管理](#体验金管理)
13. [系统监控](#系统监控)

---

## 管理员认证

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/admin/login` | POST | `Admin@login` | 管理员登录 |
| `/api/providence/admin/logout` | POST | `Admin@logout` | 管理员登出 |
| `/api/providence/admin/me` | GET | `Admin@getUserInfo` | 获取当前管理员信息 |
| `/api/providence/admin/profile` | GET | `Admin@getUserInfo` | 获取管理员资料（前端别名） |

**控制器文件**: `app/providence/controller/Admin.php`

---

## 仪表盘

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/dashboard/statistics` | GET | `AdminDashboard@statistics` | 获取统计数据 |

**控制器文件**: `app/providence/controller/AdminDashboard.php`

---

## 用户管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/users` | GET | `User@index` | 用户列表 |
| `/api/providence/users/:id` | GET | `User@detail` | 用户详情 |
| `/api/providence/users/:id/vip` | POST | `User@updateVip` | 修改VIP等级 |
| `/api/providence/users/:id/balance` | POST | `User@updateBalance` | 调整用户余额 |
| `/api/providence/users/:id/status` | POST | `User@toggleStatus` | 冻结/解冻用户 |
| `/api/providence/users/:id/reset-password` | POST | `User@resetPassword` | 重置用户密码 |
| `/api/providence/users/:id/kyc/approve` | POST | `User@approveKyc` | 审核KYC通过 |
| `/api/providence/users/:id/kyc/reject` | POST | `User@rejectKyc` | 审核KYC拒绝 |

**控制器文件**: `app/providence/controller/User.php`

**参数说明**:
- `POST /users/:id/balance`:
  - `type`: `add` 或 `sub`
  - `amount`: 金额
  - `currency`: `CNY` 或 `USDT`
  - `remark`: 备注
- `POST /users/:id/reset-password`:
  - `new_password`: 新密码

---

## 充值管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/recharge` | GET | `Recharge@index` | 充值列表 |
| `/api/providence/recharge/approve` | POST | `Recharge@approve` | 审核充值通过 |
| `/api/providence/recharge/reject` | POST | `Recharge@reject` | 审核充值拒绝 |

**控制器文件**: `app/providence/controller/Recharge.php`

**参数说明**:
- `POST /recharge/approve`:
  - `id`: 充值记录ID
  - `remark`: 备注（可选）
- `POST /recharge/reject`:
  - `id`: 充值记录ID
  - `reason`: 拒绝原因

**数据库表**: `recharge_records`

---

## 提现管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/withdraw` | GET | `Withdraw@index` | 提现列表 |
| `/api/providence/withdraw/approve` | POST | `Withdraw@approve` | 审核提现通过 |
| `/api/providence/withdraw/reject` | POST | `Withdraw@reject` | 审核提现拒绝 |

**控制器文件**: `app/providence/controller/Withdraw.php`

**参数说明**:
- `POST /withdraw/approve`:
  - `id`: 提现记录ID
  - `remark`: 备注（可选）
- `POST /withdraw/reject`:
  - `id`: 提现记录ID
  - `reason`: 拒绝原因

**数据库表**: `withdraw_records`

---

## 项目管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/projects` | GET | `Project@index` | 项目列表 |
| `/api/providence/projects/:id` | GET | `Project@detail` | 项目详情 |
| `/api/providence/projects` | POST | `Project@create` | 创建项目 |
| `/api/providence/projects/:id` | PUT | `Project@update` | 更新项目 |
| `/api/providence/projects/:id` | DELETE | `Project@delete` | 删除项目 |
| `/api/providence/projects/batch-status` | POST | `Project@batchStatus` | 批量更新项目状态 |

**控制器文件**: `app/providence/controller/Project.php`

**数据库表**: `projects`

---

## 订单管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/orders` | GET | `Order@index` | 订单列表 |
| `/api/providence/orders/:id` | GET | `Order@detail` | 订单详情 |

**控制器文件**: `app/providence/controller/Order.php`

**数据库表**: `invest_orders`

---

## 收益记录

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/earnings` | GET | `Earning@index` | 收益记录列表 |

**控制器文件**: `app/providence/controller/Earning.php`

**数据库表**: `earnings`

---

## 日利宝管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/ribao/stats` | GET | `Ribao@stats` | 日利宝统计数据 |
| `/api/providence/ribao/records` | GET | `Ribao@records` | 日利宝记录列表 |
| `/api/providence/ribao/users` | GET | `Ribao@users` | 日利宝用户列表 |
| `/api/providence/ribao/config` | GET | `Ribao@getConfig` | 获取日利宝配置 |
| `/api/providence/ribao/config` | POST | `Ribao@updateConfig` | 更新日利宝配置 |

**控制器文件**: `app/providence/controller/Ribao.php`

**数据库表**: `ribao_records`

**响应格式**:
```json
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 0,
    "totalAmount": 0.0,
    "totalProfit": 0.0,
    "activeCount": 0,
    "cnyTotal": 0.0,
    "usdtTotal": 0.0
  }
}
```

---

## 返利管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/rebate/stats` | GET | `Rebate@stats` | 返利统计数据 |
| `/api/providence/rebate/list` | GET | `Rebate@list` | 返利记录列表 |

**控制器文件**: `app/providence/controller/Rebate.php`

**数据库表**: `referral_rewards`

**响应格式**:
```json
{
  "code": 1,
  "msg": "获取成功",
  "data": {
    "total": 0,
    "totalAmount": 0.0,
    "todayAmount": 0.0,
    "monthAmount": 0.0,
    "level1Count": 0,
    "level2Count": 0
  }
}
```

---

## 系统配置

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/system/config` | GET | `System@getConfig` | 获取系统配置 |
| `/api/providence/system/config` | POST | `System@updateConfig` | 更新系统配置 |
| `/api/providence/system/vip-config` | GET | `System@getVipConfig` | 获取VIP配置 |
| `/api/providence/system/vip-config` | POST | `System@updateVipConfig` | 更新VIP配置 |
| `/api/providence/system/team-reward-config` | GET | `System@getTeamRewardConfig` | 获取团队奖励配置 |
| `/api/providence/system/team-reward-config` | POST | `System@updateTeamRewardConfig` | 更新团队奖励配置 |

**控制器文件**: `app/providence/controller/System.php`

---

## 体验金管理

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/trial-funds` | GET | `TrialFund@index` | 体验金列表 |
| `/api/providence/trial-funds/:id/recover` | POST | `TrialFund@recover` | 手动回收体验金 |
| `/api/providence/trial-funds/statistics` | GET | `TrialFund@statistics` | 体验金统计 |
| `/api/providence/trial-funds/config` | GET | `TrialFund@getConfig` | 获取体验金配置 |
| `/api/providence/trial-funds/config` | POST | `TrialFund@updateConfig` | 更新体验金配置 |

**控制器文件**: `app/providence/controller/TrialFund.php`

**数据库表**: `trial_funds`

---

## 系统监控

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/audit-logs` | GET | `Monitor@auditLogs` | 审计日志 |
| `/api/providence/login-logs` | GET | `Monitor@loginLogs` | 登录日志 |
| `/api/providence/risk/alerts` | GET | `Monitor@riskAlerts` | 风险警报 |
| `/api/providence/risk/blacklist` | GET | `Monitor@riskBlacklist` | 黑名单列表 |
| `/api/providence/risk/blacklist` | POST | `Monitor@addBlacklist` | 添加黑名单 |
| `/api/providence/risk/rules` | GET | `Monitor@riskRules` | 风控规则 |
| `/api/providence/risk/rules` | POST | `Monitor@updateRiskRules` | 更新风控规则 |

**控制器文件**: `app/providence/controller/Monitor.php`

---

## 其他功能

| 路由 | 方法 | 控制器 | 说明 |
|------|------|--------|------|
| `/api/providence/wallet-logs` | GET | `WalletLog@index` | 钱包流水 |
| `/api/providence/project-categories` | GET | `ProjectCategory@index` | 项目板块列表 |
| `/api/providence/project-categories` | POST | `ProjectCategory@create` | 创建项目板块 |
| `/api/providence/project-categories/:id` | DELETE | `ProjectCategory@delete` | 删除项目板块 |
| `/api/providence/announcements` | GET | `Content@announcements` | 公告列表 |
| `/api/providence/popups` | GET | `Content@popups` | 弹窗列表 |
| `/api/providence/popups` | POST | `Content@createPopup` | 创建弹窗 |
| `/api/providence/popups/:id` | PUT | `Content@updatePopup` | 更新弹窗 |
| `/api/providence/popups/:id` | DELETE | `Content@deletePopup` | 删除弹窗 |
| `/api/providence/activities` | GET | `Content@activities` | 活动列表 |
| `/api/providence/face-verification` | GET | `FaceVerification@index` | 人脸识别列表 |
| `/api/providence/face-verification/:id/approve` | POST | `FaceVerification@approve` | 通过人脸识别 |
| `/api/providence/face-verification/:id/reject` | POST | `FaceVerification@reject` | 拒绝人脸识别 |
| `/api/providence/face-verification/statistics` | GET | `FaceVerification@statistics` | 人脸识别统计 |
| `/api/providence/face-verification/settings` | GET | `FaceVerification@getSettings` | 获取人脸识别设置 |
| `/api/providence/face-verification/settings` | POST | `FaceVerification@updateSettings` | 更新人脸识别设置 |
| `/api/providence/team/rewards` | GET | `Team@rewards` | 团队奖励列表 |
| `/api/providence/team/rules` | GET | `Team@getRules` | 获取团队规则 |
| `/api/providence/team/rules` | POST | `Team@updateRules` | 更新团队规则 |

---

## 📝 路由定义位置

所有后台路由定义在: `app/api/route/route.php`

路由组: `Route::group('providence', function () { ... })`

---

## 🔧 响应格式

所有接口统一使用以下格式：

**成功响应**:
```json
{
  "code": 1,
  "msg": "操作成功",
  "data": { ... }
}
```

**失败响应**:
```json
{
  "code": -1,
  "msg": "错误信息",
  "data": null
}
```

---

## ⚠️ 注意事项

1. 所有路由都在 `Route::group('providence', function () { ... })` 中定义
2. 控制器命名空间: `app\providence\controller\`
3. 数据库表名严格按照 `FULL_API_DATABASE_DOC.md` 中的定义
4. 所有接口都有异常处理，即使表不存在也会返回空数据，避免500错误
5. 参数验证严格按照文档规范

---

**最后更新**: 2025-11-26
