<?php

namespace App\Support\Sms\Providers;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Sms\SmsMessage;
use App\Support\Sms\SmsProvider;
use App\Support\Sms\SmsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * RayganSMS / Trez (https://raygansms.com). HTTPS endpoints as used by the vendor's own
 * `trezrayan/raygan-sms` package:
 *  - text: POST RayganSMS.com/SendMessageWithPost.ashx (UserName, Password, PhoneNumber, Smsclass, RecNumber, MessageBody)
 *  - code: GET  smspanel.trez.ir/SendMessageWithCode.ashx (UserName, Password, Mobile, Message), the OTP service line
 * The API returns a bare number: a message id (> 1000) or 2 on success, a small status code on
 * failure (Trez: 0/3 failed, 4 no credit, 5 too long, 6/8 bad credentials). Credentials are never
 * logged. Confirm the parsing on the first live send (the vendor doesn't document the JSON shape).
 */
final class RayganSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $sender,
        private readonly string $codeTemplate = "کد ورود شما: :code\n:app",
        private readonly string $textUrl = 'https://RayganSMS.com/SendMessageWithPost.ashx',
        private readonly string $codeUrl = 'https://smspanel.trez.ir/SendMessageWithCode.ashx',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        // The endpoint takes one recipient per call.
        $last = SmsResult::failure('no_recipients');
        foreach ($message->recipients as $phone) {
            $last = $this->call('text', fn () => $this->http->asForm()->timeout($this->timeoutSeconds)->post($this->textUrl, [
                'UserName' => $this->username, 'Password' => $this->password, 'PhoneNumber' => (string) $this->sender,
                'Smsclass' => '1', 'RecNumber' => PhoneNormalizer::toLocal($phone), 'MessageBody' => $message->text,
            ]));
            if (! $last->successful) {
                return $last;
            }
        }

        return $last;
    }

    public function sendVerificationCode(string $phoneE164, string $code): SmsResult
    {
        $text = strtr($this->codeTemplate, [':code' => $code, ':app' => (string) config('app.name')]);

        return $this->call('code', fn () => $this->http->timeout($this->timeoutSeconds)->get($this->codeUrl, [
            'UserName' => $this->username, 'Password' => $this->password, 'Mobile' => PhoneNormalizer::toLocal($phoneE164), 'Message' => $text,
        ]));
    }

    /** @param  callable(): Response  $request */
    private function call(string $kind, callable $request): SmsResult
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            Log::warning('[sms:raygan] connection failed', ['kind' => $kind]);

            return SmsResult::failure('connection');
        }

        $value = trim((string) $response->body(), " \t\n\r\0\x0B\"");
        $json = $response->json();
        if (is_array($json)) {
            $value = (string) ($json['Code'] ?? $json['code'] ?? $json['Result'] ?? $json['result'] ?? $json[0] ?? '');
        }

        // Trez: a message id (> 1000) or 2 («sent without saving») mean sent; 0–8 are errors.
        if ($response->successful() && is_numeric($value) && ((int) $value > 1000 || (int) $value === 2)) {
            return SmsResult::success($value);
        }

        Log::warning('[sms:raygan] request rejected', ['kind' => $kind, 'http_status' => $response->status(), 'provider_status' => mb_substr($value, 0, 40)]);

        return SmsResult::failure('provider_status_'.($value !== '' ? mb_substr($value, 0, 20) : $response->status()));
    }
}
