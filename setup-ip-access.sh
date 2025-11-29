#!/bin/bash
# 配置IP直接访问API服务
# 用于72.60.234.87或其他服务器

SERVER_IP=${1:-"72.60.234.87"}
API_PATH="/www/wwwroot/api.4kp3l0iq.top/public"

echo "=========================================="
echo "  配置IP直接访问API服务"
echo "  目标服务器: $SERVER_IP"
echo "=========================================="
echo ""

# 检查是否在目标服务器上
CURRENT_IP=$(hostname -I | awk '{print $1}')
if [ "$CURRENT_IP" != "$SERVER_IP" ]; then
    echo "⚠️  当前服务器IP: $CURRENT_IP"
    echo "⚠️  目标服务器IP: $SERVER_IP"
    echo ""
    echo "请确认是否要在当前服务器配置，或使用SSH连接到目标服务器执行此脚本"
    read -p "继续执行？(y/n): " confirm
    if [ "$confirm" != "y" ]; then
        exit 1
    fi
fi

# 检查API目录是否存在
if [ ! -d "$API_PATH" ]; then
    echo "❌ API目录不存在: $API_PATH"
    echo "请先部署API服务到该目录"
    exit 1
fi

# 备份原配置
DEFAULT_CONF="/www/server/panel/vhost/nginx/0.default.conf"
if [ -f "$DEFAULT_CONF" ]; then
    BACKUP_FILE="${DEFAULT_CONF}.bak.$(date +%Y%m%d_%H%M%S)"
    cp "$DEFAULT_CONF" "$BACKUP_FILE"
    echo "✅ 已备份原配置: $BACKUP_FILE"
fi

# 创建新的默认站点配置
cat > /tmp/default_api_ip.conf << 'NGINX_EOF'
server
{
    listen 80;
    listen 443 ssl http2;
    server_name _;
    index index.php index.html;
    root /www/wwwroot/api.4kp3l0iq.top/public;

    # SSL配置（如果存在）
    ssl_certificate    /www/server/panel/vhost/cert/0.default/fullchain.pem;
    ssl_certificate_key    /www/server/panel/vhost/cert/0.default/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers EECDH+CHACHA20:EECDH+CHACHA20-draft:EECDH+AES128:RSA+AES128:EECDH+AES256:RSA+AES256:EECDH+3DES:RSA+3DES:!MD5;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    add_header Strict-Transport-Security "max-age=31536000";

    # 增加请求体大小限制（用于KYC图片上传）
    client_max_body_size 20M;

    # CORS 跨域配置
    set $cors_origin $http_origin;
    if ($http_origin = '') {
        set $cors_origin '*';
    }
    add_header Access-Control-Allow-Origin $cors_origin always;
    add_header Access-Control-Allow-Credentials 'true' always;
    add_header Access-Control-Allow-Methods 'GET, POST, PUT, DELETE, OPTIONS, PATCH' always;
    add_header Access-Control-Allow-Headers 'Authorization, Content-Type, Token, token, X-Token, X-Requested-With, Accept, Origin' always;
    add_header Access-Control-Expose-Headers 'Authorization, Token, token' always;
    add_header Access-Control-Max-Age '86400' always;

    if ($request_method = OPTIONS) {
        return 204;
    }

    access_log /www/wwwlogs/default_api_ip.log;
    error_log /www/wwwlogs/default_api_ip.error.log;

    # Bot API
    location ~ ^/bot_api.php(/.*)?$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index bot_api.php;
        fastcgi_param SCRIPT_FILENAME $document_root/bot_api.php;
        include fastcgi_params;
    }

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }

    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX_EOF

# 替换API路径
sed -i "s|/www/wwwroot/api.4kp3l0iq.top/public|$API_PATH|g" /tmp/default_api_ip.conf

# 检查PHP-FPM socket路径
PHP_SOCKET=""
if [ -S "/tmp/php-cgi-82.sock" ]; then
    PHP_SOCKET="unix:/tmp/php-cgi-82.sock"
elif [ -S "/run/php/php8.2-fpm.sock" ]; then
    PHP_SOCKET="unix:/run/php/php8.2-fpm.sock"
elif [ -S "/run/php/php8.3-fpm.sock" ]; then
    PHP_SOCKET="unix:/run/php/php8.3-fpm.sock"
else
    echo "⚠️  未找到PHP-FPM socket，请手动修改配置中的fastcgi_pass路径"
fi

if [ -n "$PHP_SOCKET" ]; then
    sed -i "s|unix:/tmp/php-cgi-82.sock|$PHP_SOCKET|g" /tmp/default_api_ip.conf
    echo "✅ 已设置PHP-FPM socket: $PHP_SOCKET"
fi

# 应用配置
cp /tmp/default_api_ip.conf "$DEFAULT_CONF"
echo "✅ 已更新默认站点配置"

# 测试Nginx配置
echo ""
echo "测试Nginx配置..."
if nginx -t 2>&1 | grep -q "successful"; then
    echo "✅ Nginx配置测试通过"

    # 重载Nginx
    nginx -s reload
    echo "✅ Nginx已重载"

    echo ""
    echo "=========================================="
    echo "  🎉 配置完成！"
    echo "=========================================="
    echo ""
    echo "现在可以通过以下方式访问API："
    echo "  - http://$SERVER_IP/"
    echo "  - http://$SERVER_IP/api/project/index"
    echo "  - https://$SERVER_IP/ (如果SSL证书已配置)"
    echo ""
    echo "测试命令："
    echo "  curl http://$SERVER_IP/"
    echo "  curl http://$SERVER_IP/api/project/index"
    echo ""
else
    echo "❌ Nginx配置测试失败"
    echo "请检查错误信息并修复配置"
    exit 1
fi
