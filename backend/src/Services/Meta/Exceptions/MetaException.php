<?php

declare(strict_types=1);

namespace App\Services\Meta\Exceptions;

use RuntimeException;

/** Base for all Meta Graph/Marketing API errors. */
class MetaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $metaCode = null,
        public readonly ?int $metaSubcode = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
