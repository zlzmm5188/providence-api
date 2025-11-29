# API Smoke Test Result

**测试时间**: 2025-11-27 11:20:08
**测试环境**: Production (api.4kp3l0iq.top)

---

## providence/ribao/stats

**URL**: `https://api.4kp3l0iq.top/api/providence/ribao/stats`
**HTTP状态码**: `200`

**状态**: ✅ 通过
**响应体**: ```json
{"code":1,"msg":"获取成功","data":{"total":0,"totalAmount":0,"totalProfit":0,"activeCount":0,"cnyTotal":0,"usdtTotal":0}}

```

---

## providence/ribao/records?page=1&pageSize=10

**URL**: `https://api.4kp3l0iq.top/api/providence/ribao/records?page=1&pageSize=10`
**HTTP状态码**: `200`

**状态**: ✅ 通过
**响应体**: ```json
{"code":1,"msg":"获取成功","data":{"list":[],"total":0}}

```

---

## providence/ribao/users?page=1&pageSize=10

**URL**: `https://api.4kp3l0iq.top/api/providence/ribao/users?page=1&pageSize=10`
**HTTP状态码**: `200`

**状态**: ✅ 通过
**响应体**: ```json
{"code":1,"msg":"获取成功","data":{"list":[],"total":0}}

```

---

## providence/rebate/stats

**URL**: `https://api.4kp3l0iq.top/api/providence/rebate/stats`
**HTTP状态码**: `200`

**状态**: ✅ 通过
**响应体**: ```json
{"code":1,"msg":"获取成功","data":{"total":0,"totalAmount":0,"todayAmount":0,"monthAmount":0,"level1Count":0,"level2Count":0}}

```

---

## providence/rebate/list?page=1&pageSize=10

**URL**: `https://api.4kp3l0iq.top/api/providence/rebate/list?page=1&pageSize=10`
**HTTP状态码**: `200`

**状态**: ✅ 通过
**响应体**: ```json
{"code":1,"msg":"获取成功","data":{"list":[],"total":0}}

```

---

## 测试总结

- **通过**: 5
- **失败**: 0
- **总计**: 5

**结果**: ✅ 所有接口测试通过
