<?php

namespace App\Modules\Payments\Support\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Zarinpal REST v4. Amounts are sent in rial (`currency: IRR`), matching our storage.
 * Codes: 100 = success, 101 = already verified (also success). Everything else is a failure.
 * The customer is sent to /pg/StartPay/{authority} and returns to the callback with
 * ?Authority=…&Status=OK|NOK. That Status is never trusted: the payment is always verified.
 */
final class ZarinpalGateway implements PaymentGateway
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $merchantId,
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function name(): string
    {
        return 'zarinpal';
    }

    public function request(int $amount, string $callbackUrl, string $description, ?string $mobile = null, ?string $orderId = null): GatewayResult
    {
        $payload = [
            'amount' => $amount,
            'currency' => 'IRR',
            'callback_url' => $callbackUrl,
            'description' => mb_substr($description, 0, 250),
            'metadata' => array_filter(['mobile' => $mobile, 'order_id' => $orderId]),
        ];

        return $this->call('/pg/v4/payment/request.json', $payload, function (array $data) use ($payload): GatewayResult {
            $code = $this->dataCode($data);
            $authority = $data['data']['authority'] ?? null;

            if ($code === '100' && is_string($authority) && $authority !== '') {
                return new GatewayResult(
                    success: true,
                    code: $code,
                    authority: $authority,
                    redirectUrl: $this->startUrl($authority, ''),
                    request: $payload,
                    response: $data,
                );
            }

            return new GatewayResult(success: false, code: $this->errorCode($data), request: $payload, response: $data);
        });
    }

    public function startUrl(string $authority, string $callbackUrl): string
    {
        return $this->baseUrl.'/pg/StartPay/'.rawurlencode($authority);
    }

    public function verify(int $amount, string $authority): GatewayResult
    {
        $payload = ['amount' => $amount, 'authority' => $authority];

        return $this->call('/pg/v4/payment/verify.json', $payload, function (array $data) use ($payload): GatewayResult {
            $code = $this->dataCode($data);

            if (in_array($code, ['100', '101'], true)) {
                return new GatewayResult(
                    success: true,
                    code: $code,
                    refId: isset($data['data']['ref_id']) ? (string) $data['data']['ref_id'] : null,
                    cardPan: isset($data['data']['card_pan']) ? (string) $data['data']['card_pan'] : null,
                    fee: isset($data['data']['fee']) ? (int) $data['data']['fee'] : null,
                    request: $payload,
                    response: $data,
                );
            }

            return new GatewayResult(success: false, code: $this->errorCode($data), request: $payload, response: $data);
        });
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    /**
     * Posts with the merchant ID added. The result only carries the payload without it.
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(array<string, mixed>): GatewayResult  $interpret
     */
    private function call(string $path, array $payload, callable $interpret): GatewayResult
    {
        $started = hrtime(true);

        try {
            $response = $this->http->asJson()->acceptJson()->timeout($this->timeout)
                ->post($this->baseUrl.$path, ['merchant_id' => $this->merchantId, ...$payload]);
        } catch (ConnectionException $e) {
            return new GatewayResult(success: false, transient: true, code: 'network', request: $payload, response: ['error' => mb_substr($e->getMessage(), 0, 300)], durationMs: $this->elapsed($started));
        }

        $data = $response->json();

        if ($response->serverError() || ! is_array($data)) {
            return new GatewayResult(
                success: false,
                transient: true,
                code: 'http_'.$response->status(),
                request: $payload,
                response: ['body' => mb_substr($response->body(), 0, 500)],
                httpStatus: $response->status(),
                durationMs: $this->elapsed($started),
            );
        }

        /** @var array<string, mixed> $data */
        return $interpret($data)->withTiming($response->status(), $this->elapsed($started));
    }

    /** @param  array<string, mixed>  $data */
    private function dataCode(array $data): string
    {
        $code = is_array($data['data'] ?? null) ? ($data['data']['code'] ?? '') : '';

        return is_scalar($code) ? (string) $code : '';
    }

    /** @param  array<string, mixed>  $data */
    private function errorCode(array $data): string
    {
        $code = (is_array($data['errors'] ?? null) ? ($data['errors']['code'] ?? null) : null) ?? $this->dataCode($data);

        return is_scalar($code) && (string) $code !== '' ? (string) $code : 'unknown';
    }

    private function elapsed(int|float $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
