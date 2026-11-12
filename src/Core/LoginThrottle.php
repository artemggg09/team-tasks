<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ограничение попыток входа: после 5 неудач подряд — пауза 60 секунд.
 * Счётчик хранится в БД по паре «IP + email», поэтому не сбрасывается вместе с cookie.
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_SECONDS = 60;

    public static function key(string $email, string $ip): string
    {
        return hash('sha256', mb_strtolower($email) . '|' . $ip);
    }

    /**
     * Сколько секунд осталось до разблокировки (0 — вход разрешён).
     */
    public static function secondsLeft(string $key): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT attempted_at FROM login_attempts WHERE throttle_key = ? ORDER BY attempted_at DESC LIMIT ' . self::MAX_ATTEMPTS);
        $stmt->execute([$key]);
        $times = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        if (count($times) < self::MAX_ATTEMPTS) {
            return 0;
        }
        $left = $times[0] + self::LOCK_SECONDS - time();
        if ($left > 0) {
            return $left;
        }
        // Пауза прошла — даём новые 5 попыток
        self::clear($key);
        return 0;
    }

    public static function hit(string $key): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO login_attempts (throttle_key, attempted_at) VALUES (?, ?)');
        $stmt->execute([$key, time()]);
    }

    public static function clear(string $key): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM login_attempts WHERE throttle_key = ?');
        $stmt->execute([$key]);
    }
}
