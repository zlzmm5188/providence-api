#!/bin/bash
# Railway项目初始化脚本
# 在登录后执行此脚本

echo "🚂 初始化Railway项目..."

# 初始化项目
railway init --name providence-api || railway link

# 显示项目信息
railway status

echo ""
echo "✅ 初始化完成！"
echo "下一步: 设置环境变量并执行 railway up"
