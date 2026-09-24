<?php

namespace App\Support\Sms\Providers;

use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Sms\SmsResult;
use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes messages to the log instead of sending them.
 * It is refused outside local/testing (see SmsServiceProvider), so codes never leak in production logs.
 */
final class LogSmsProvider implements SmsProvider
{
    public function send(SmsMessage $message): SmsResult
    {
        Log::info('[sms:log] message', ['to' => $message->recipients, 'text' => $message->text]);

        return SmsResult::success('log');
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        Log::info('[sms:log] verification code', ['to' => $phoneE164, 'code' => $code]);

        return SmsResult::success('log');
    }
}
