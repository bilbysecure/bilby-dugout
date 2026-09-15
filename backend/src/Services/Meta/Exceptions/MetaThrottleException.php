<?php

declare(strict_types=1);

namespace App\Services\Meta\Exceptions;

/** Raised when Meta throttles us and retries with backoff are exhausted. */
final class MetaThrottleException extends MetaException
{
}
