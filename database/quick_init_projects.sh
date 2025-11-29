#!/bin/bash
# 快速初始化项目脚本

echo "🚀 Providence 项目初始化工具"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "请选择初始化方式："
echo "1. 使用SQL脚本（推荐）"
echo "2. 使用PHP脚本"
echo ""
read -p "请输入选项 (1/2): " choice

case $choice in
    1)
        echo ""
        echo "📋 使用SQL脚本初始化..."
        echo "请执行以下命令："
        echo ""
        echo "mysql -u root -p providence < database/init_sample_projects.sql"
        echo ""
        echo "或者直接在数据库中执行 SQL 文件内容"
        ;;
    2)
        echo ""
        echo "📋 使用PHP脚本初始化..."
        php database/init_sample_projects.php
        ;;
    *)
        echo "❌ 无效选项"
        exit 1
        ;;
esac

echo ""
echo "✅ 初始化完成！"
echo ""
echo "📋 后台管理地址："
echo "   🌐 生产环境: https://admin.4kp3l0iq.top"
echo "   🔧 开发环境: http://localhost:5667"
echo ""
echo "📊 项目列表："
echo "   👑 VIP项目（CNY）: 5个"
echo "   💎 USDT项目: 4个"
echo "   💵 普通项目（CNY）: 2个"
