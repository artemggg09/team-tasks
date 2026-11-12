<?php

declare(strict_types=1);

/*
 * Общая инициализация для веба и CLI-скриптов.
 */

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Запасной PSR-4 автозагрузчик, если composer install не запускался
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require BASE_PATH . '/src/helpers.php';
}

$timezone = (string) config('timezone');
date_default_timezone_set(in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Europe/Moscow');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', is_production() ? '0' : '1');

$logFile = (string) config('log');
if ($logFile !== '') {
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    ini_set('error_log', $logFile);
}
