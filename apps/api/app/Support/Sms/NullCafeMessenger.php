<?php

namespace App\Support\Sms;

/** Default when no messaging module is installed: cafés send nothing. */
final class NullCafeMessenger implements CafeMessenger
{
    public function template(string $key, string $phoneE164, array $values): void {}

    public function send(string $kind, array $recipients, string $text): void {}
}
