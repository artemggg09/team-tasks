#!/bin/sh
set -e

# Render и Railway передают порт в $PORT
PORT="${PORT:-80}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ровно один MPM — prefork (нужен mod_php). Повторяем при запуске: на некоторых платформах
# (Railway) лишний MPM оказывается включён, и Apache падает с «AH00534: More than one MPM loaded»
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    a2enmod mpm_prefork >/dev/null
fi

# storage/ может быть подключён как Persistent Disk — выставляем права на запись
mkdir -p /var/www/html/storage
cd /var/www/html

# Таблицы создаются и обновляются идемпотентно. Тестовые данные (сид) на сервере не создаются —
# пользователи регистрируются сами. Для стенда можно задать SEED_DEMO=1.
php database/migrate.php
if [ "${SEED_DEMO:-0}" = "1" ]; then
    php database/seed.php
fi

chown -R www-data:www-data /var/www/html/storage

exec "$@"
