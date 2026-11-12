<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * С хешем пароля — только для проверки входа.
     */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
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

    public static function create(string $name, string $email, string $password): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $name,
            mb_strtolower($email),
            password_hash($password, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }
}
