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
     * Создаёт таблицы (идемпотентно: CREATE TABLE IF NOT EXISTS).
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

        // v2: дата завершения задачи (для «темпа недели») в уже существующих БД
        if (!self::hasColumn('tasks', 'completed_at')) {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL');
            $pdo->exec("UPDATE tasks SET completed_at = updated_at WHERE status = 'done'");
        }

        // v3: вход по логину — email заменяется на username
        if (self::hasColumn('users', 'email')) {
            self::migrateEmailToUsername($pdo);
        }

        // v4: логин до 32 символов (в SQLite длина VARCHAR не ограничивается — менять нечего)
        if (self::driver() === 'mysql') {
            $stmt = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username'");
            if ((int) $stmt->fetchColumn() < 32) {
                $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(32) NOT NULL');
            }
        }

        if (self::driver() === 'sqlite') {
            $pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
        }
    }

    /**
     * Логин берётся из части email до «@» (только допустимые символы, при совпадении — с номером), колонка email удаляется.
     */
    private static function migrateEmailToUsername(PDO $pdo): void
    {
        $users = $pdo->query('SELECT id, email FROM users ORDER BY id')->fetchAll();
        $taken = [];
        $logins = [];
        foreach ($users as $u) {
            $base = \App\Models\User::usernameFromEmail((string) $u['email']);
            $login = $base;
            for ($i = 2; isset($taken[$login]); $i++) {
                $login = substr($base, 0, 29) . $i;
            }
            $taken[$login] = true;
            $logins[(int) $u['id']] = $login;
        }

        if (self::driver() === 'sqlite') {
            // SQLite не умеет удалять UNIQUE-колонку — пересобираем таблицу
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->beginTransaction();
            $pdo->exec('CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                username VARCHAR(32) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
            $insert = $pdo->prepare('INSERT INTO users_new (id, name, username, password_hash, created_at) SELECT id, name, ?, password_hash, created_at FROM users WHERE id = ?');
            foreach ($logins as $id => $login) {
                $insert->execute([$login, $id]);
            }
            $pdo->exec('DROP TABLE users');
            $pdo->exec('ALTER TABLE users_new RENAME TO users');
            $pdo->commit();
            $pdo->exec('PRAGMA foreign_keys = ON');
            return;
        }

        // MySQL: DDL не транзакционный, шаги идемпотентны
        if (!self::hasColumn('users', 'username')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(32) NULL AFTER name');
        }
        $update = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
        foreach ($logins as $id => $login) {
            $update->execute([$login, $id]);
        }
        $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(32) NOT NULL, ADD UNIQUE KEY users_username_unique (username)');
        $pdo->exec('ALTER TABLE users DROP COLUMN email');
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $pdo = self::connection();
        if (self::driver() === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() !== false;
    }
}
