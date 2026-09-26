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
 * FarazSMS / IPPanel REST (https://api2.ippanel.com/api/v1/sms/send/webservice/single):
 * header `apikey`, JSON {recipient[], sender, message}. Success is `status == "OK"` (data.message_id).
 */
final class IppanelSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly string $sender,
        private readonly string $url = 'https://api2.ippanel.com/api/v1/sms/send/webservice/single',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        try {
            $response = $this->http->withHeaders(['apikey' => $this->apiKey])->acceptJson()->timeout($this->timeoutSeconds)->post($this->url, [
                'recipient' => array_map(PhoneNormalizer::toLocal(...), $message->recipients),
                'sender' => $this->sender,
                'message' => $message->text,
            ]);
        } catch (ConnectionException) {
            Log::warning('[sms:ippanel] connection failed');

            return SmsResult::failure('connection');
        }

        $status = (string) $response->json('status');
        if ($response->successful() && strtoupper($status) === 'OK') {
            return SmsResult::success((string) ($response->json('data.message_id') ?? ''));
        }
        Log::warning('[sms:ippanel] request rejected', ['http_status' => $response->status(), 'provider_status' => $status, 'code' => $response->json('code')]);

        return SmsResult::failure('provider_status_'.($status !== '' ? $status : $response->status()));
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        return $this->send(new SmsMessage([$phoneE164], "کد تأیید: {$code}"));
    }
}
