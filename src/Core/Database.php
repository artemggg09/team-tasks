<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

final class Database
{
    /** Увеличивать при изменении схемы */
    public const SCHEMA_VERSION = 4;

    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = (string) config('db.dsn');
        $isSqlite = str_starts_with($dsn, 'sqlite:');
        $needsMigration = false;

        if ($isSqlite) {
            $path = substr($dsn, strlen('sqlite:'));
            if ($path !== ':memory:' && preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path) !== 1) {
                $path = BASE_PATH . '/' . $path; // относительный путь — от корня проекта
            }
            if ($path === ':memory:' || !is_file($path)) {
                $needsMigration = true;
                $dir = dirname($path);
                if ($path !== ':memory:' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new RuntimeException('Не удалось создать каталог для БД: ' . $dir);
                }
            }
            $dsn = 'sqlite:' . $path;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // PDO::connect() (PHP 8.4+) возвращает драйверный подкласс, например Pdo\Sqlite
        $pdo = method_exists(PDO::class, 'connect')
            ? PDO::connect($dsn, config('db.user'), config('db.pass'), $options)
            : new PDO($dsn, config('db.user'), config('db.pass'), $options);

        if ($isSqlite) {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            // SQLite LOWER() не понимает кириллицу — регистрируем свою функцию
            $lower = static fn (?string $v): ?string => $v === null ? null : mb_strtolower($v);
            if (method_exists($pdo, 'createFunction')) {
                $pdo->createFunction('ulower', $lower, 1); // PHP 8.4+ (Pdo\Sqlite)
            } else {
                $pdo->sqliteCreateFunction('ulower', $lower, 1);
            }
        } elseif (str_starts_with($dsn, 'mysql:')) {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        self::$pdo = $pdo;

        // Для SQLite версия схемы хранится в PRAGMA user_version — обновляемся сами
        if ($needsMigration || ($isSqlite && (int) $pdo->query('PRAGMA user_version')->fetchColumn() < self::SCHEMA_VERSION)) {
            self::migrate();
        }

        return $pdo;
    }

    public static function driver(): string
    {
        return (string) self::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Имя функции перевода в нижний регистр, корректной для UTF-8.
     */
    public static function lowerFn(): string
    {
        return self::driver() === 'sqlite' ? 'ulower' : 'LOWER';
    }

    /**
     * Создаёт таблицы по актуальной схеме (идемпотентно: CREATE TABLE IF NOT EXISTS).
     */
    public static function migrate(): void
    {
        $pdo = self::connection();
        $file = self::driver() === 'mysql' ? 'schema.mysql.sql' : 'schema.sql';
        $sql = file_get_contents(BASE_PATH . '/database/' . $file);
        if ($sql === false) {
            throw new RuntimeException('Не найден файл схемы ' . $file);
        }
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }

        if (self::driver() === 'sqlite') {
            $pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
        }
    }
}
