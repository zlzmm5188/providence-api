<?php
// api应用中间件定义文件
// 2025-11-24 修复：移除全局 AuthMiddleware，改为在路由分组中明确绑定
// 理由：login 和 register 接口不需要 Token 验证
return [
    // 全局中间件已移除
    // CORS跨域中间件已在 public/index.php 统一处理
    // AuthMiddleware 已在 route/api.php 的各路由分组中明确绑定
];
