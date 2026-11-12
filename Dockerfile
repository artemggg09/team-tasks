# --- Автозагрузчик Composer (PSR-4) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
COPY src ./src
RUN composer dump-autoload --optimize --no-dev

# --- Приложение ---
FROM php:8.2-apache

# pdo_sqlite уже входит в официальный образ; добавляем pdo_mysql для перехода на MySQL
RUN docker-php-ext-install pdo_mysql \
    && php -m | grep -q pdo_sqlite \
    && a2enmod rewrite headers expires

# DocumentRoot → public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\nServerTokens Prod\nServerSignature Off\n' \
       > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Продакшен-настройки PHP: ошибки не выводятся, пишутся в лог (stderr контейнера)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nerror_log=/dev/stderr\n' > "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage \
    && chown -R www-data:www-data storage

ENV APP_ENV=production \
    APP_LOG="" \
    DB_DSN=sqlite:storage/database.sqlite

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
