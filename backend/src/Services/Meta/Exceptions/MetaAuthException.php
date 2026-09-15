<?php

declare(strict_types=1);

namespace App\Services\Meta\Exceptions;

/** Raised for token/permission failures (e.g. code 190 — invalid/expired token). */
final class MetaAuthException extends MetaException
{
}
