<?php

namespace App\Support\Sms;

final readonly class SmsMessage
{
    /**
     * @param  list<string>  $recipients  E.164 phone numbers
     */
    public function __construct(
        public array $recipients,
        public string $text,
    ) {}
}
