FROM php:8.1-cli

# 安裝必要套件與 PHP 擴充
RUN apt-get update && apt-get install -y \
    unzip zip curl git \
    libpq-dev libzip-dev libonig-dev \
    libicu-dev zlib1g-dev g++ \
    procps supervisor \
    sysstat \
    net-tools \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 設定工作目錄
WORKDIR /app

# 複製專案與 Supervisor 設定
COPY ./app /app
COPY ./app/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 安裝 PHP 相依套件
RUN composer install --no-interaction --no-progress \
    && chmod +x rr || true \
    && chown -R www-data:www-data /app \
    && mkdir -p /var/log/supervisor

# 預設執行啟動 script
CMD ["bash", "/app/start_service.sh"]
