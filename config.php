<?php

declare(strict_types=1);

/*
 * Конфигурация приложения. Значения берутся из переменных окружения,
 * а локально — из файла .env (переменные окружения имеют приоритет).
 */

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

return [
    'name'     => 'TeamTasks',
    'env'      => $env('APP_ENV', 'production'),
    'url'      => rtrim((string) $env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => $env('APP_TIMEZONE', 'Europe/Moscow'),
    // Путь к логу; пустая строка — писать в стандартный лог SAPI (stderr в Docker)
    'log'      => $env('APP_LOG', __DIR__ . '/storage/logs/app.log'),
    'db' => [
        'dsn'  => $env('DB_DSN', 'sqlite:storage/database.sqlite'),
        'user' => $env('DB_USER', '') ?: null,
        'pass' => $env('DB_PASS', '') ?: null,
    ],
];
