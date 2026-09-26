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
 * SMS.ir v1 (https://api.sms.ir/v1/send/bulk): header `x-api-key`, JSON body
 * {lineNumber, messageText, mobiles[]}. Success is `status == 1` (data.packId).
 */
final class SmsIrSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly string $lineNumber,
        private readonly string $url = 'https://api.sms.ir/v1/send/bulk',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        try {
            $response = $this->http->withHeaders(['x-api-key' => $this->apiKey])->acceptJson()->timeout($this->timeoutSeconds)->post($this->url, [
                'lineNumber' => is_numeric($this->lineNumber) ? (int) $this->lineNumber : $this->lineNumber,
                'messageText' => $message->text,
                'mobiles' => array_map(PhoneNormalizer::toLocal(...), $message->recipients),
            ]);
        } catch (ConnectionException) {
            Log::warning('[sms:smsir] connection failed');

            return SmsResult::failure('connection');
        }

        $status = $response->json('status');
        if ($response->successful() && (int) $status === 1) {
            return SmsResult::success((string) ($response->json('data.packId') ?? ''));
        }
        Log::warning('[sms:smsir] request rejected', ['http_status' => $response->status(), 'provider_status' => $status]);

        return SmsResult::failure('provider_status_'.($status ?? $response->status()));
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        return $this->send(new SmsMessage([$phoneE164], "کد تأیید: {$code}"));
    }
}
