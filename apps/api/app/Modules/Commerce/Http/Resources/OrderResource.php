<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderItemModifier;
use App\Modules\Commerce\Models\OrderStatusHistory;
use App\Modules\Core\Support\TenantSettings;
use App\Support\Localization\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as staff and its customer see it. Amounts are integer rial.
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'daily_number' => $this->daily_number,
            'business_date' => $this->business_date->toDateString(),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'table' => $this->whenLoaded('table', fn () => $this->table ? ['id' => $this->table->id, 'label' => $this->table->label] : null),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'source' => $this->source->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'next_statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], $this->status->allowedNext($this->type)),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'payment_method_intent' => $this->payment_method_intent,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            // When a pre-order goes to the kitchen; until then the board keeps it under «پیش‌سفارش‌ها».
            'kitchen_release_at' => $this->scheduled_for?->copy()->subMinutes((int) TenantSettings::get('preorder.release_minutes'))->toIso8601String(),
            'customer_note' => $this->customer_note,
            'customer_id' => $this->customer_id,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone_e164 ? PhoneNormalizer::toLocal($this->contact_phone_e164) : null,
            'address' => $this->address_snapshot,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'discount' => $this->discount_snapshot ? ['name' => $this->discount_snapshot['name'], 'code' => $this->discount_snapshot['code']] : null,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'refunded_total' => $this->refunded_total,
            'remaining_due' => $this->remainingDue(),
            'needs_refund' => $this->needsRefund(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'variant_name' => $i->variant_name,
                'unit_price' => $i->unit_price,
                'modifiers_total' => $i->modifiers_total,
                'quantity' => $i->quantity,
                'line_total' => $i->line_total,
                'note' => $i->note,
                'modifiers' => $i->modifiers->map(fn (OrderItemModifier $m) => ['group_name' => $m->group_name, 'name' => $m->name, 'price_delta' => $m->price_delta])->values(),
            ])->values()),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn (OrderStatusHistory $h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'to_label' => $h->to_status->label(),
                'note' => $h->note,
                'at' => $h->created_at->toIso8601String(),
            ])->values()),
            'placed_at' => $this->placed_at->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            // The customer's own history links each order to live tracking.
            'tracking_token' => $this->when($request->routeIs('api.customer.orders.index'), fn () => $this->trackingToken()),
        ];
    }
}
