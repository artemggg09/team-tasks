<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\UsernameTakenException;
use InvalidArgumentException;
use PDOException;

final class User
{
    /** Логин после нормализации: 3–32 символа, латиница в нижнем регистре, цифры, подчёркивание */
    public const USERNAME_PATTERN = '/^[a-z0-9_]{3,32}$/';
    public const NAME_MIN = 1;
    public const NAME_MAX = 100;
    public const PASSWORD_MIN_BYTES = 12;
    public const PASSWORD_MAX_BYTES = 72;

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, username, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * С хешем пароля — только для проверки входа.
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([self::normalize($username)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int, array{id:int, name:string}> */
    public static function all(): array
    {
        return Database::connection()->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
    }

    /**
     * Создаёт пользователя. created_at проставляет БД (DEFAULT CURRENT_TIMESTAMP).
     * Уникальность логина обеспечивает UNIQUE-индекс — без предварительного SELECT (нет гонки).
     *
     * @throws InvalidArgumentException при неверных имени, логине или пароле
     * @throws UsernameTakenException   если логин уже занят
     */
    public static function create(string $name, string $username, string $password): int
    {
        $name = trim($name);
        $username = self::normalize($username);
        self::assertValid($name, $username, $password);

        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)');
        try {
            $stmt->execute([
                $name,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            if (self::isDuplicateKey($e)) {
                throw new UsernameTakenException($username, $e);
            }
            throw $e;
        }
        return (int) $db->lastInsertId();
    }

    /**
     * Вызывается после успешного password_verify(): если хеш создан со старым алгоритмом
     * или стоимостью, пересчитывает его с текущими настройками (пароль в открытом виде есть только здесь).
     *
     * @return bool true — хеш обновлён
     */
    public static function rehashIfNeeded(int $id, string $currentHash, string $password): bool
    {
        if (!password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
            return false;
        }
        $stmt = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
        // Условие по старому хешу: не перезаписать пароль, если его успели сменить параллельно
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id, $currentHash]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Правила те же, что в форме регистрации (AuthController) — модель не доверяет вызывающему коду.
     */
    private static function assertValid(string $name, string $username, string $password): void
    {
        $nameLength = mb_strlen($name, 'UTF-8');
        if ($nameLength < self::NAME_MIN || $nameLength > self::NAME_MAX) {
            throw new InvalidArgumentException('Имя: от ' . self::NAME_MIN . ' до ' . self::NAME_MAX . ' символов.');
        }
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new InvalidArgumentException('Логин не соответствует формату ' . self::USERNAME_PATTERN . '.');
        }
        // bcrypt учитывает только первые 72 байта — длинные пароли отклоняем, а не обрезаем молча
        $bytes = strlen($password);
        if ($bytes < self::PASSWORD_MIN_BYTES || $bytes > self::PASSWORD_MAX_BYTES) {
            throw new InvalidArgumentException('Пароль: от ' . self::PASSWORD_MIN_BYTES . ' до ' . self::PASSWORD_MAX_BYTES . ' байт.');
        }
    }

    /**
     * SQLSTATE 23000 — нарушение ограничения целостности. Уточняем по коду драйвера,
     * чтобы не выдать за «логин занят» другие ошибки класса 23000 (NOT NULL, внешний ключ).
     */
    private static function isDuplicateKey(PDOException $e): bool
    {
        if ($e->getCode() !== '23000') {
            return false;
        }
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        return $driverCode === 1062                                   // MySQL / MariaDB: ER_DUP_ENTRY
            || str_contains($e->getMessage(), 'UNIQUE constraint failed'); // SQLite
    }

    /**
     * Одна нормализация для регистрации и входа (findByUsername, AuthController::login).
     */
    public static function normalize(string $username): string
    {
        return mb_strtolower(trim($username), 'UTF-8');
    }

    /**
     * Логин из старого email (для миграции): часть до «@», только допустимые символы.
     */
    public static function usernameFromEmail(string $email): string
    {
        $local = strtolower((string) strstr($email, '@', true) ?: $email);
        $login = substr((string) preg_replace('/[^a-z0-9_]+/', '', $local), 0, 32);
        return strlen($login) >= 3 ? $login : str_pad($login, 3, '0') ;
    }
}
