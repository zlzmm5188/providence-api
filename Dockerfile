# 使用 PHP 7.4 FPM + Nginx
FROM php:7.4-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd

# 安装 Redis
RUN pecl install redis && docker-php-ext-enable redis

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 复制代码
COPY . .

# 安装依赖
RUN composer install --no-dev --optimize-autoloader || true

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p runtime && chmod -R 777 runtime

# 配置 Nginx
RUN echo 'server {\n\
    listen 80;\n\
    root /var/www/html/public;\n\
    index index.php;\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    location ~ \.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
}' > /etc/nginx/sites-available/default

# 创建启动脚本
RUN echo '#!/bin/bash\n\
php-fpm -D\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

# 健康检查文件
RUN echo '<?php echo "OK"; ?>' > /var/www/html/public/health.php

EXPOSE 80

CMD ["/start.sh"]
