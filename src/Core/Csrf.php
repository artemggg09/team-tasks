<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        $token = $_SESSION[self::KEY] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::KEY] = $token;
        }
        return $token;
    }

    /**
     * Токен из поля формы _csrf или заголовка X-CSRF-Token (для fetch).
     */
    public static function verify(): bool
    {
        $sent = $_POST[self::KEY] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = $_SESSION[self::KEY] ?? '';
        return is_string($sent) && is_string($stored) && $stored !== '' && hash_equals($stored, $sent);
    }
}
