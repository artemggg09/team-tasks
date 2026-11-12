<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            self::$user = is_int($id) ? User::find($id) : null;
            if ($id !== null && self::$user === null) {
                unset($_SESSION['user_id']); // пользователь удалён
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(array $user): void
    {
        Session::regenerate(); // защита от фиксации сессии
        $_SESSION['user_id'] = (int) $user['id'];
        unset($_SESSION['_csrf']);
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
        Session::start();
        Session::regenerate();
    }
}
