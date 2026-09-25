<?php

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\PurchaseOrderItem;
use App\Modules\Inventory\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrder */
final class PurchaseOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => PurchaseOrder::STATUS_LABELS[$this->status] ?? $this->status,
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name]),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name]),
            'expected_on' => $this->expected_on?->toDateString(),
            'total' => $this->total,
            'paid_total' => $this->paid_total,
            'balance_due' => $this->balanceDue(),
            'has_receipts' => $this->hasReceipts(),
            'note' => $this->note,
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PurchaseOrderItem $i) => [
                'id' => $i->id,
                'ingredient' => ['id' => $i->ingredient->id, 'name' => $i->ingredient->name, 'unit' => $i->ingredient->unit->value, 'pack_label' => $i->ingredient->pack_label, 'pack_size' => $i->ingredient->pack_size !== null ? (float) $i->ingredient->pack_size : null],
                'quantity' => (float) $i->quantity,
                'received_quantity' => (float) $i->received_quantity,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total,
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (SupplierPayment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'method' => $p->method,
                'method_label' => SupplierPayment::METHODS[$p->method] ?? $p->method,
                'note' => $p->note,
                'paid_at' => $p->paid_at->toIso8601String(),
            ])->values()),
        ];
    }
}
