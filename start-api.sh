#!/bin/bash
# Providence API启动脚本

cd "$(dirname "$0")/public"
echo "启动Providence API服务..."
echo "地址: http://localhost:8082"
echo "按Ctrl+C停止服务"

# 使用PHP内置服务器，指定index.php作为路由器
php -S localhost:8082 -t . index.php
