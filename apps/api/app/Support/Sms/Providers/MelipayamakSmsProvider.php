<?php

namespace App\Support\Sms\Providers;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Melipayamak / Payamak Panel REST (https://rest.payamak-panel.com/api/SendSMS/SendSMS):
 * form fields username, password, to (comma separated), from, text, isflash. Success is
 * `RetStatus == 1` (then `Value` is the message id). Credentials are never logged.
 */
final class MelipayamakSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $username,
        private readonly string $password,
        private readonly string $sender,
        private readonly string $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        try {
            $response = $this->http->asForm()->acceptJson()->timeout($this->timeoutSeconds)->post($this->url, [
                'username' => $this->username, 'password' => $this->password, 'from' => $this->sender,
                'to' => implode(',', array_map(PhoneNormalizer::toLocal(...), $message->recipients)), 'text' => $message->text, 'isflash' => 'false',
            ]);
        } catch (ConnectionException) {
            Log::warning('[sms:melipayamak] connection failed');

            return SmsResult::failure('connection');
        }

        $status = $response->json('RetStatus');
        if ($response->successful() && (int) $status === 1) {
            return SmsResult::success((string) $response->json('Value'));
        }
        Log::warning('[sms:melipayamak] request rejected', ['http_status' => $response->status(), 'provider_status' => $status]);

        return SmsResult::failure('provider_status_'.($status ?? $response->status()));
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        return $this->send(new SmsMessage([$phoneE164], "کد تأیید: {$code}"));
    }
}
