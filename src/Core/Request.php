<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * Строковое значение из $_POST / $_GET (массивы и прочее отбрасываются).
     */
    public static function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * @param string[] $keys
     * @return array<string, string>
     */
    public static function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = self::input($key);
        }
        return $data;
    }

    public static function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json');
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Безопасный адрес возврата: только внутренний /tasks с query-строкой.
     */
    public static function back(string $fallback = '/tasks'): string
    {
        $back = self::input('back');
        if (preg_match('#^/tasks(\?[^\s\\\\]*)?$#', $back) === 1) {
            return $back;
        }
        return $fallback;
    }
}
