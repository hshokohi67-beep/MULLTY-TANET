<?php

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Support\FailureMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment as staff see it. Amounts are integer rial. The gateway authority is never exposed.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'daily_number' => $this->order->daily_number,
                'business_date' => $this->order->business_date->toDateString(),
                'status' => $this->order->status->value,
                'status_label' => $this->order->status->label(),
            ]),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'gateway' => $this->gateway,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => $this->amount,
            'refunded_amount' => $this->refunded_amount,
            'refundable' => $this->refundable(),
            'ref_id' => $this->ref_id,
            'card_pan' => $this->card_pan,
            'fee' => $this->fee,
            'reference' => $this->reference,
            'note' => $this->note,
            'failure_code' => $this->failure_code,
            'failure_message' => FailureMessage::for($this->failure_code),
            'refunds' => $this->whenLoaded('refunds', fn () => $this->refunds->map(fn (PaymentRefund $r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'method' => $r->method->value,
                'method_label' => $r->method->label(),
                'reference' => $r->reference,
                'reason' => $r->reason,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values()),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
