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
 * Kavenegar adapter (https://kavenegar.com/rest.html), HTTPS only, no SDK dependency.
 * The API key lives in the URL path, so it must never be logged. Only status codes are logged.
 */
final class KavenegarSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly ?string $sender,
        private readonly string $verifyTemplate,
        private readonly string $baseUrl = 'https://api.kavenegar.com/v1',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        $payload = [
            'receptor' => implode(',', array_map(PhoneNormalizer::toLocal(...), $message->recipients)),
            'message' => $message->text,
        ];

        if ($this->sender) {
            $payload['sender'] = $this->sender;
        }

        return $this->call('sms/send.json', $payload);
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        return $this->call('verify/lookup.json', [
            'receptor' => PhoneNormalizer::toLocal($phoneE164),
            'token' => $code,
            'template' => $this->verifyTemplate,
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function call(string $endpoint, array $payload): SmsResult
    {
        try {
            $response = $this->http
                ->asForm()
                ->timeout($this->timeoutSeconds)
                ->post(sprintf('%s/%s/%s', rtrim($this->baseUrl, '/'), $this->apiKey, $endpoint), $payload);
        } catch (ConnectionException) {
            Log::warning('[sms:kavenegar] connection failed', ['endpoint' => $endpoint]);

            return SmsResult::failure('connection');
        }

        $status = $response->json('return.status');

        if ($response->successful() && (int) $status === 200) {
            return SmsResult::success((string) ($response->json('entries.0.messageid') ?? ''));
        }

        Log::warning('[sms:kavenegar] request rejected', [
            'endpoint' => $endpoint,
            'http_status' => $response->status(),
            'provider_status' => $status,
        ]);

        return SmsResult::failure('provider_status_'.($status ?? $response->status()));
    }
}
