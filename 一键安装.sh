#!/bin/bash

echo "========================================"
echo "  Providence PHP后端 - 一键安装脚本"
echo "========================================"

# 1. 检查PHP
echo ""
echo "1️⃣  检查PHP版本..."
php -v
if [ $? -ne 0 ]; then
    echo "❌ 未检测到PHP，请先安装PHP 8.0+"
    exit 1
fi

# 2. 安装Composer依赖
echo ""
echo "2️⃣  安装Composer依赖..."
if [ ! -f "composer.phar" ]; then
    echo "下载Composer..."
    curl -sS https://getcomposer.org/installer | php
fi

php composer.phar install
if [ $? -ne 0 ]; then
    echo "❌ Composer依赖安装失败"
    exit 1
fi

# 3. 配置环境变量
echo ""
echo "3️⃣  配置环境变量..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ 已创建.env文件，请修改数据库配置"
else
    echo "⚠️  .env文件已存在，跳过"
fi

# 4. 询问是否导入数据库
echo ""
echo "4️⃣  数据库配置"
read -p "是否导入数据库？(y/n): " import_db

if [ "$import_db" = "y" ]; then
    read -p "MySQL主机 [127.0.0.1]: " db_host
    db_host=${db_host:-127.0.0.1}

    read -p "MySQL端口 [3306]: " db_port
    db_port=${db_port:-3306}

    read -p "MySQL用户名 [root]: " db_user
    db_user=${db_user:-root}

    read -sp "MySQL密码: " db_pass
    echo ""

    read -p "数据库名 [providence]: " db_name
    db_name=${db_name:-providence}

    # 创建数据库
    echo "创建数据库..."
    mysql -h${db_host} -P${db_port} -u${db_user} -p${db_pass} -e "CREATE DATABASE IF NOT EXISTS ${db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    # 导入SQL
    echo "导入数据表..."
    mysql -h${db_host} -P${db_port} -u${db_user} -p${db_pass} ${db_name} < ../database_simplified.sql

    if [ $? -eq 0 ]; then
        echo "✅ 数据库导入成功"

        # 更新.env
        sed -i.bak "s/hostname = .*/hostname = ${db_host}/" .env
        sed -i.bak "s/database = .*/database = ${db_name}/" .env
        sed -i.bak "s/username = .*/username = ${db_user}/" .env
        sed -i.bak "s/password = .*/password = ${db_pass}/" .env
        sed -i.bak "s/hostport = .*/hostport = ${db_port}/" .env
        rm -f .env.bak

        echo "✅ .env配置已更新"
    else
        echo "❌ 数据库导入失败"
        exit 1
    fi
fi

# 5. 设置权限
echo ""
echo "5️⃣  设置目录权限..."
mkdir -p runtime
chmod -R 777 runtime

echo ""
echo "========================================"
echo "  🎉 安装完成！"
echo "========================================"
echo ""
echo "📝 下一步："
echo "1. 修改 .env 文件配置数据库"
echo "2. 运行开发服务器："
echo "   php think run -p 8888"
echo ""
echo "3. 测试API："
echo "   注册: POST http://localhost:8888/api/auth/register"
echo "   登录: POST http://localhost:8888/api/auth/login"
echo ""
echo "4. 前台地址: /Users/lulu/zijinzuixin/qiantai"
echo "5. 管理后台地址: /Users/lulu/zijinzuixin/qianduan"
echo ""
echo "========================================"
