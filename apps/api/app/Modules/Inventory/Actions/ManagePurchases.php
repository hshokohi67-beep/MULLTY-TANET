<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InventoryException;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\IngredientStock;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\PurchaseOrderItem;
use App\Modules\Inventory\Models\SupplierPayment;
use App\Modules\Inventory\Support\Units;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Purchase orders: draft → ordered → received (partly or fully) or cancelled, plus supplier
 * payments. Receiving posts `purchase` stock movements and updates each ingredient's weighted
 * average cost; lines are typed in purchase units (kg, L, pack…) and stored in base units.
 */
final class ManagePurchases
{
    public function __construct(
        private readonly PostStockMovement $stock,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{supplier_id: string, branch_id: string, expected_on?: ?string, note?: ?string, items: list<array{ingredient_id: string, quantity: float, unit?: ?string, unit_price: int}>}  $data
     */
    public function save(array $data, ?PurchaseOrder $po, string $actorId): PurchaseOrder
    {
        if ($po !== null && $po->status !== 'draft') {
            throw InventoryException::purchaseNotEditable();
        }

        return DB::transaction(function () use ($data, $po, $actorId): PurchaseOrder {
            $isNew = $po === null;
            $po ??= new PurchaseOrder([
                // Sequential per café; the unique index catches a race and the request is retried by the client.
                'number' => (int) PurchaseOrder::query()->lockForUpdate()->max('number') + 1,
                'status' => 'draft',
                'created_by' => $actorId,
            ]);
            $po->fill([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'],
                'expected_on' => $data['expected_on'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $po->save();

            $po->items()->delete();
            $ingredients = Ingredient::query()->whereIn('id', array_column($data['items'], 'ingredient_id'))->get()->keyBy('id');
            $total = 0;
            foreach ($data['items'] as $line) {
                $ingredient = $ingredients->get($line['ingredient_id']);
                if ($ingredient === null) {
                    continue;
                }
                $qty = Units::toBase($ingredient, (float) $line['quantity'], $line['unit'] ?? null);
                $unitPrice = Units::pricePer1000($ingredient, (int) $line['unit_price'], $line['unit'] ?? null);
                $lineTotal = (int) round($qty * $unitPrice / 1000);
                $po->items()->create(['ingredient_id' => $ingredient->id, 'quantity' => $qty, 'unit_price' => $unitPrice, 'line_total' => $lineTotal]);
                $total += $lineTotal;
            }
            $po->forceFill(['total' => $total])->save();

            $this->audit->record($isNew ? 'purchase.created' : 'purchase.updated', $po, ['total' => $total]);

            return $po->load('items.ingredient');
        });
    }

    public function markOrdered(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw InventoryException::purchaseNotEditable();
        }
        $po->forceFill(['status' => 'ordered', 'ordered_at' => now()])->save();

        return $po;
    }

    /**
     * Receive stock. Without lines everything still outstanding is received at the ordered price;
     * lines may receive part of an item and correct its price (per purchase unit).
     *
     * @param  list<array{item_id: string, quantity: float, unit?: ?string, unit_price?: ?int}>|null  $lines
     */
    public function receive(PurchaseOrder $po, ?array $lines, string $actorId, string $key): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $lines, $actorId, $key): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['draft', 'ordered'], true)) {
                throw InventoryException::purchaseClosed();
            }

            $items = $locked->items()->with('ingredient')->get()->keyBy('id');
            $plan = $lines ?? $items->map(fn (PurchaseOrderItem $i) => ['item_id' => $i->id, 'quantity' => max(0, (float) $i->quantity - (float) $i->received_quantity), 'unit' => null])->values()->all();

            foreach ($plan as $n => $line) {
                $item = $items->get($line['item_id']);
                if ($item === null) {
                    continue;
                }
                $qty = Units::toBase($item->ingredient, (float) $line['quantity'], $line['unit'] ?? null);
                if ($qty <= 0) {
                    continue;
                }
                if (isset($line['unit_price'])) {
                    $item->unit_price = Units::pricePer1000($item->ingredient, (int) $line['unit_price'], $line['unit'] ?? null);
                }

                $this->updateAverageCost($item->ingredient, $qty, $item->unit_price);
                $this->stock->handle($item->ingredient_id, $locked->branch_id, StockMovementType::Purchase, $qty, "receive:{$key}:{$n}", [
                    'unit_cost' => $item->unit_price,
                    'purchase_order_id' => $locked->id,
                    'note' => "خرید #{$locked->number}",
                    'actor_type' => 'user',
                    'actor_id' => $actorId,
                ]);

                $item->received_quantity = number_format((float) $item->received_quantity + $qty, 3, '.', '');
                // The bill follows what actually arrived once receiving starts.
                $item->line_total = (int) round(max((float) $item->quantity, (float) $item->received_quantity) * $item->unit_price / 1000);
                $item->save();
            }

            $items = $locked->items()->get();
            $complete = $items->every(fn (PurchaseOrderItem $i) => (float) $i->received_quantity >= (float) $i->quantity - 0.0005);
            $locked->forceFill([
                'total' => (int) $items->sum('line_total'),
                'status' => $complete ? 'received' : 'ordered',
                'ordered_at' => $locked->ordered_at ?? now(),
                'received_at' => $complete ? now() : null,
            ])->save();

            $this->audit->record('purchase.received', $locked, ['complete' => $complete]);

            return $locked->load('items.ingredient', 'payments');
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        if (! in_array($po->status, ['draft', 'ordered'], true)) {
            throw InventoryException::purchaseClosed();
        }
        if ($po->items()->where('received_quantity', '>', 0)->exists()) {
            throw InventoryException::purchaseHasReceipts();
        }
        $po->forceFill(['status' => 'cancelled'])->save();
        $this->audit->record('purchase.cancelled', $po, []);

        return $po;
    }

    public function pay(PurchaseOrder $po, int $amount, string $method, ?string $note, string $actorId): SupplierPayment
    {
        return DB::transaction(function () use ($po, $amount, $method, $note, $actorId): SupplierPayment {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                throw InventoryException::purchaseClosed();
            }
            if ($amount > $locked->balanceDue()) {
                throw InventoryException::overpayment(MoneyFormatter::format(Money::rials($locked->balanceDue())));
            }

            $payment = $locked->payments()->create(['amount' => $amount, 'method' => $method, 'note' => $note, 'paid_at' => now(), 'recorded_by' => $actorId]);
            $locked->forceFill(['paid_total' => $locked->paid_total + $amount])->save();
            $this->audit->record('purchase.paid', $locked, ['amount' => $amount]);

            return $payment;
        });
    }

    /** Weighted moving average over what is on hand (all branches; negative stock counts as none). */
    private function updateAverageCost(Ingredient $ingredient, float $qty, int $unitPrice): void
    {
        /** @var Ingredient $locked */
        $locked = Ingredient::query()->whereKey($ingredient->id)->lockForUpdate()->firstOrFail();
        $onHand = max(0.0, (float) IngredientStock::query()->where('ingredient_id', $locked->id)->sum('quantity'));
        $locked->avg_cost = (int) round(($onHand * $locked->avg_cost + $qty * $unitPrice) / ($onHand + $qty));
        $locked->save();
        $ingredient->avg_cost = $locked->avg_cost;
    }
}
