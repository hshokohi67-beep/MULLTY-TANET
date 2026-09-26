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
 * RayganSMS / Trez (https://raygansms.com):
 *  - text: POST RayganSMS.com/SendMessageWithPost.ashx (UserName, Password, PhoneNumber, Smsclass, RecNumber,
 *    MessageBody); a message id (> 1000) or 2 means sent.
 *  - login code: POST smspanel.trez.ir/AutoSendCode.ashx (UserName, Password, Mobile, Footer). Per Trez's docs a
 *    3–8 digit Footer is sent as the code itself (so we keep generating and verifying it in OtpService), it
 *    reaches blacklisted numbers too, and an answer above 2000 means sent; 8 means the web service isn't
 *    enabled on the account.
 * Other answers are small status codes (0/3 failed, 4 no credit, 5 too long, 6/8 credentials or access).
 * Credentials are never logged.
 */
final class RayganSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $sender,
        private readonly string $textUrl = 'https://RayganSMS.com/SendMessageWithPost.ashx',
        private readonly string $codeUrl = 'https://smspanel.trez.ir/AutoSendCode.ashx',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function send(SmsMessage $message): SmsResult
    {
        // The endpoint takes one recipient per call.
        $last = SmsResult::failure('no_recipients');
        foreach ($message->recipients as $phone) {
            $last = $this->call('text', fn (int $v) => $v > 1000 || $v === 2, fn () => $this->http->asForm()->timeout($this->timeoutSeconds)->post($this->textUrl, [
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
        // Our own code goes in Footer (3–8 digits), so verification stays in OtpService.
        return $this->call('code', fn (int $v) => $v > 2000, fn () => $this->http->asForm()->timeout($this->timeoutSeconds)->post($this->codeUrl, [
            'UserName' => $this->username, 'Password' => $this->password, 'Mobile' => PhoneNormalizer::toLocal($phoneE164), 'Footer' => $code,
        ]));
    }

    /**
     * @param  callable(int): bool  $sent  which numeric answers mean "sent" for this endpoint
     * @param  callable(): Response  $request
     */
    private function call(string $kind, callable $sent, callable $request): SmsResult
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

        if ($response->successful() && is_numeric($value) && $sent((int) $value)) {
            return SmsResult::success($value);
        }

        Log::warning('[sms:raygan] request rejected', ['kind' => $kind, 'http_status' => $response->status(), 'provider_status' => mb_substr($value, 0, 40)]);

        return SmsResult::failure('provider_status_'.($value !== '' ? mb_substr($value, 0, 20) : $response->status()));
    }
}
