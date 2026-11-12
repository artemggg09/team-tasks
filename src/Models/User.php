<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    /** Логин: 3–30 символов, латиница, цифры, точка, дефис, подчёркивание; хранится в нижнем регистре */
    public const USERNAME_PATTERN = '/^[a-z0-9._-]{3,30}$/';

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

    public static function usernameExists(string $username): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([self::normalize($username)]);
        return $stmt->fetchColumn() !== false;
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

    public static function create(string $name, string $username, string $password): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO users (name, username, password_hash, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $name,
            self::normalize($username),
            password_hash($password, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function normalize(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    /**
     * Логин из старого email (для миграции): часть до «@», только допустимые символы.
     */
    public static function usernameFromEmail(string $email): string
    {
        $local = strtolower((string) strstr($email, '@', true) ?: $email);
        $login = substr((string) preg_replace('/[^a-z0-9._-]+/', '', $local), 0, 30);
        return strlen($login) >= 3 ? $login : str_pad($login, 3, '0') ;
    }
}
