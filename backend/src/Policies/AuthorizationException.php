<?php

declare(strict_types=1);

namespace App\Policies;

use RuntimeException;

/** Thrown when a Principal fails a policy check → rendered as HTTP 403. */
final class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message);
    }
}
