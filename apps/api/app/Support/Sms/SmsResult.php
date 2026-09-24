<?php

namespace App\Support\Sms;

final readonly class SmsResult
{
    private function __construct(
        public bool $successful,
        public ?string $providerReference,
        public ?string $error,
    ) {}

    public static function success(?string $providerReference = null): self
    {
        return new self(true, $providerReference, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
