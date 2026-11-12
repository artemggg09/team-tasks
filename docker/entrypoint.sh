#!/bin/sh
set -e

# Render и Railway передают порт в $PORT
PORT="${PORT:-80}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# storage/ может быть подключён как Persistent Disk — выставляем права на запись
mkdir -p /var/www/html/storage
cd /var/www/html

# Таблицы создаются идемпотентно, демо-данные — только в пустую БД
php database/migrate.php
php database/seed.php

chown -R www-data:www-data /var/www/html/storage

exec "$@"
