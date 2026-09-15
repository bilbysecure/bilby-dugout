<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;
use Throwable;

/** Authentication failure (bad credentials, MFA required, token invalid). */
final class AuthException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
