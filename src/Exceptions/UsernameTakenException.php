<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Логин уже занят: INSERT нарушил UNIQUE-индекс users.username.
 */
final class UsernameTakenException extends RuntimeException
{
    public function __construct(public readonly string $username, ?\Throwable $previous = null)
    {
        parent::__construct("Username «{$username}» is already taken", 0, $previous);
    }
}
