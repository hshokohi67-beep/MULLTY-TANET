<?php

namespace App\Support\Sms\Providers;

use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Sms\SmsResult;

/**
 * Test driver: keeps everything in memory so tests can assert what was sent.
 */
final class ArraySmsProvider implements SmsProvider
{
    /** @var list<SmsMessage> */
    public array $messages = [];

    /** @var list<array{phone: string, code: string}> */
    public array $verificationCodes = [];

    public function send(SmsMessage $message): SmsResult
    {
        $this->messages[] = $message;

        return SmsResult::success('array');
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        $this->verificationCodes[] = ['phone' => $phoneE164, 'code' => $code];

        return SmsResult::success('array');
    }

    public function lastCodeFor(string $phoneE164): ?string
    {
        foreach (array_reverse($this->verificationCodes) as $entry) {
            if ($entry['phone'] === $phoneE164) {
                return $entry['code'];
            }
        }

        return null;
    }
}
