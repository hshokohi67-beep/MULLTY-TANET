<?php

namespace App\Modules\Payments\Support\Gateways;

use Illuminate\Support\Str;

/**
 * Local/testing stand-in: "redirects" straight back to the callback as if the customer paid.
 * An amount whose last two rial digits are 13 is declined, so the failure path can be tried by hand.
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function request(int $amount, string $callbackUrl, string $description, ?string $mobile = null, ?string $orderId = null): GatewayResult
    {
        $authority = 'FAKE'.Str::upper(Str::random(28));

        return new GatewayResult(
            success: true,
            code: '100',
            authority: $authority,
            redirectUrl: $this->startUrl($authority, $callbackUrl),
            request: ['amount' => $amount, 'callback_url' => $callbackUrl],
            response: ['authority' => $authority],
        );
    }

    public function startUrl(string $authority, string $callbackUrl): string
    {
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';

        return $callbackUrl.$separator.http_build_query(['Authority' => $authority, 'Status' => 'OK']);
    }

    public function verify(int $amount, string $authority): GatewayResult
    {
        if ($amount % 100 === 13) {
            return new GatewayResult(success: false, code: '-51', request: ['amount' => $amount], response: ['code' => -51]);
        }

        return new GatewayResult(
            success: true,
            code: '100',
            refId: (string) random_int(100_000_000, 999_999_999),
            cardPan: '6037********1234',
            fee: 0,
            request: ['amount' => $amount],
            response: ['code' => 100],
        );
    }

    public function supportsRefunds(): bool
    {
        return false;
    }
}
