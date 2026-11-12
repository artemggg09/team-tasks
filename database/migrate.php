<?php

declare(strict_types=1);

/*
 * Создание таблиц: php database/migrate.php
 * Безопасно запускать повторно (CREATE TABLE IF NOT EXISTS).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

Database::migrate();
echo 'Миграция выполнена (' . Database::driver() . ')' . PHP_EOL;
