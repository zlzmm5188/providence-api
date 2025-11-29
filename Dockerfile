# 用官方 PHP 镜像 - 不用 FPM 和 Nginx 的复杂方案，用 Apache！
FROM php:8.0-apache

# 安装系统依赖
RUN apt-get update && apt-get install -y \
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

# 启用 mod_rewrite
RUN a2enmod rewrite

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 复制代码
COPY . .

# 安装 PHP 依赖
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader 2>&1 | tail -5; fi

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 创建运行时目录
RUN mkdir -p /var/www/html/runtime && chmod -R 777 /var/www/html/runtime

# 配置 Apache vhost
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        RewriteEngine On\n\
        RewriteCond %{REQUEST_FILENAME} !-f\n\
        RewriteCond %{REQUEST_FILENAME} !-d\n\
        RewriteRule ^(.*)$ index.php [L,QSA]\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# 启用 vhost
RUN a2ensite 000-default

# 创建健康检查
RUN echo "<?php http_response_code(200); echo 'OK'; ?>" > /var/www/html/public/health.php

EXPOSE 80

CMD ["apache2-foreground"]
