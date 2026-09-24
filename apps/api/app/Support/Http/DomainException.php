<?php

namespace App\Support\Http;

use RuntimeException;

/**
 * A business-rule violation with a user-facing Persian message and a stable machine code.
 * Rendered as {"message": ..., "code": ...} with the given HTTP status.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(
        string $userMessage,
        public readonly string $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($userMessage);
    }
}
