# 后台API错误扫描报告

**扫描时间**: 2025-11-26
**扫描范围**: 后台管理API接口

---

## ✅ 已修复的错误

### 1. 投资订单接口错误
**接口**: `GET /api/invest/orders`

**错误信息**:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'order_id' in 'order clause'
```

**错误原因**:
- `InvestOrder`模型定义的主键是`order_id`，但数据库表的主键是`id`
- 排序时使用了`order('order_id', 'desc')`，但表中没有`order_id`字段

**修复方案**:
1. 修改`InvestOrder`模型：`protected $pk = 'id'`
2. 修改排序：`order('id', 'desc')`
3. 修改字段访问：`$order->order_id` → `$order->id`
4. 修改查询条件：`where('order_id', $orderId)` → `where('id', $orderId)`

**修复文件**:
- `/app/common/model/InvestOrder.php`
- `/app/api/controller/Invest.php`

**状态**: ✅ 已修复并测试通过

---

### 2. 提现记录接口错误
**接口**: `GET /api/withdraw/list`

**错误信息**:
```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'providence.withdraw_orders' doesn't exist
```

**错误原因**:
- `WithdrawOrder`模型使用的表名是`withdraw_orders`
- 数据库中的实际表名是`withdraw_records`

**修复方案**:
1. 修改`WithdrawOrder`模型：`protected $table = 'withdraw_records'`
2. 修改`TelegramBotAdminService`中所有`withdraw_orders`为`withdraw_records`

**修复文件**:
- `/app/common/model/WithdrawOrder.php`
- `/app/common/service/TelegramBotAdminService.php`

**状态**: ✅ 已修复并测试通过

---

## ✅ 正常工作的接口

| 接口 | 状态 | 说明 |
|------|------|------|
| `POST /api/admin/login` | ✅ 正常 | 管理员登录 |
| `POST /api/admin/getUserInfo` | ✅ 正常 | 获取管理员信息 |
| `POST /api/admin/getPermissions` | ✅ 正常 | 获取权限 |
| `GET /api/user/info` | ✅ 正常 | 用户信息 |
| `GET /api/project/index` | ✅ 正常 | 项目列表 |
| `GET /api/team/members` | ✅ 正常 | 团队成员 |
| `GET /api/recharge/list` | ✅ 正常 | 充值记录 |

---

## 📋 测试结果汇总

### 测试通过 (7个)
- ✅ 管理员登录
- ✅ 获取管理员信息
- ✅ 获取权限
- ✅ 用户信息
- ✅ 项目列表
- ✅ 团队成员
- ✅ 充值记录

### 修复后通过 (2个)
- ✅ 投资订单（已修复）
- ✅ 提现记录（已修复）

---

## 🔍 其他发现

### 字段映射问题
- 数据库使用`start_date`/`end_date`，代码中部分使用`start_time`/`end_time`
- **状态**: ✅ 已修复（统一使用`start_date`/`end_date`）

### 主键字段问题
- `InvestOrder`模型主键定义错误
- **状态**: ✅ 已修复（改为`id`）

### 表名不一致
- `WithdrawOrder`模型表名错误
- **状态**: ✅ 已修复（改为`withdraw_records`）

---

## 📝 建议

1. **统一字段命名**: 确保模型定义与数据库表结构一致
2. **代码审查**: 定期检查模型定义和数据库表结构的一致性
3. **单元测试**: 为关键接口添加单元测试，提前发现类似问题

---

**报告生成时间**: 2025-11-26
**修复状态**: ✅ 所有错误已修复
