<?php

namespace App\Modules\Payments\Support\Gateways;

/**
 * Outcome of one gateway call. `transient` means the outcome is unknown (timeout, 5xx):
 * the caller must not treat it as a failure, because the customer may have paid.
 */
final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $request  sanitised: never contains credentials
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public bool $success,
        public bool $transient = false,
        public ?string $code = null,
        public ?string $authority = null,
        public ?string $redirectUrl = null,
        public ?string $refId = null,
        public ?string $cardPan = null,
        public ?int $fee = null,
        public array $request = [],
        public array $response = [],
        public ?int $httpStatus = null,
        public int $durationMs = 0,
    ) {}

    public function withTiming(?int $httpStatus, int $durationMs): self
    {
        return new self(
            $this->success, $this->transient, $this->code, $this->authority, $this->redirectUrl, $this->refId,
            $this->cardPan, $this->fee, $this->request, $this->response, $httpStatus, $durationMs,
        );
    }
}
