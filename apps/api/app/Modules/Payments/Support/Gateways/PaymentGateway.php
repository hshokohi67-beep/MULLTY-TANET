<?php

namespace App\Modules\Payments\Support\Gateways;

/**
 * An online payment gateway. Implementations never throw for gateway or network errors:
 * they return a {@see GatewayResult} so every call can be logged and handled uniformly.
 * Amounts are integer rial.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Opens a payment session and returns the authority + URL to send the customer to. */
    public function request(int $amount, string $callbackUrl, string $description, ?string $mobile = null, ?string $orderId = null): GatewayResult;

    /** Where to send the customer for an already-opened session (lets a retry reuse it). */
    public function startUrl(string $authority, string $callbackUrl): string;

    /** Confirms the payment server-to-server, always with the amount we stored. */
    public function verify(int $amount, string $authority): GatewayResult;

    /** Whether refunds can be executed through the API (otherwise they are recorded after being done in the panel). */
    public function supportsRefunds(): bool;
}
