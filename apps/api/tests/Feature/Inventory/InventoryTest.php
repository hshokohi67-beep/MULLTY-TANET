<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Models\IngredientStock;
use App\Modules\Inventory\Models\OrderItemCost;
use App\Modules\Inventory\Models\StockMovement;
use Tests\Feature\Commerce\CommerceTestCase;

/**
 * A small café: beans (per kg), milk and almond milk (per litre), cups (per piece).
 * Latte = 18 g beans + 200/300 ml milk + 1 cup; «شیر بادام» swaps 200 ml milk for almond milk.
 */
final class InventoryTest extends CommerceTestCase
{
    /** @var array<string, string> */
    private array $ing = [];

    /** @return array<string, string> */
    private function h(): array
    {
        return $this->staffHeaders($this->owner, $this->tenant);
    }

    private function stock(string $key): float
    {
        return (float) $this->inTenant($this->tenant, fn () => IngredientStock::query()->where('ingredient_id', $this->ing[$key])->where('branch_id', $this->branch->id)->value('quantity'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'beans' => ['name' => 'قهوه‌ی عربیکا', 'unit' => 'g', 'cost_per_big_unit' => 2_000_000],
            'milk' => ['name' => 'شیر', 'unit' => 'ml', 'cost_per_big_unit' => 400_000, 'pack_label' => 'پاکت ۱ لیتری', 'pack_size' => 1000],
            'almond' => ['name' => 'شیر بادام', 'unit' => 'ml', 'cost_per_big_unit' => 1_500_000],
            'cup' => ['name' => 'لیوان کاغذی', 'unit' => 'pcs', 'cost_per_big_unit' => 20_000, 'low_stock_threshold' => 50],
        ] as $key => $data) {
            $this->ing[$key] = $this->postJson('/api/v1/inventory/ingredients', $data, $this->h())->assertCreated()->json('data.id');
        }

        [$small, $large] = [$this->variant($this->latte, 0), $this->variant($this->latte, 1)];
        $this->putJson("/api/v1/catalog/products/{$this->latte->id}/recipe", [
            'variants' => [
                ['variant_id' => $small, 'items' => [['ingredient_id' => $this->ing['beans'], 'quantity' => 18], ['ingredient_id' => $this->ing['milk'], 'quantity' => 200], ['ingredient_id' => $this->ing['cup'], 'quantity' => 1]]],
                ['variant_id' => $large, 'items' => [['ingredient_id' => $this->ing['beans'], 'quantity' => 18], ['ingredient_id' => $this->ing['milk'], 'quantity' => 0.3, 'unit' => 'l'], ['ingredient_id' => $this->ing['cup'], 'quantity' => 1]]],
            ],
            'modifiers' => [
                ['modifier_id' => $this->milkOption('شیر بادام'), 'items' => [['ingredient_id' => $this->ing['almond'], 'quantity' => 200], ['ingredient_id' => $this->ing['milk'], 'quantity' => -200]]],
            ],
        ], $this->h())->assertOk();
        $this->putJson("/api/v1/catalog/products/{$this->espresso->id}/recipe", [
            'variants' => [['variant_id' => $this->variant($this->espresso), 'items' => [['ingredient_id' => $this->ing['beans'], 'quantity' => 18], ['ingredient_id' => $this->ing['cup'], 'quantity' => 1]]]],
        ], $this->h())->assertOk();
    }

    private function receiveOpeningStock(): string
    {
        $supplier = $this->postJson('/api/v1/inventory/suppliers', ['name' => 'پخش قهوه', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹'], $this->h())->assertCreated()->json('data.id');
        $po = $this->postJson('/api/v1/inventory/purchases', [
            'supplier_id' => $supplier,
            'branch_id' => $this->branch->id,
            'items' => [
                ['ingredient_id' => $this->ing['beans'], 'quantity' => 2, 'unit' => 'kg', 'unit_price' => 2_200_000],
                ['ingredient_id' => $this->ing['milk'], 'quantity' => 10, 'unit' => 'pack', 'unit_price' => 400_000],
                ['ingredient_id' => $this->ing['almond'], 'quantity' => 2, 'unit' => 'l', 'unit_price' => 1_500_000],
                ['ingredient_id' => $this->ing['cup'], 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 25_000],
            ],
        ], $this->h())->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', 4_400_000 + 4_000_000 + 3_000_000 + 2_500_000)
            ->json('data.id');

        $this->postJson("/api/v1/inventory/purchases/{$po}/order", [], $this->h())->assertOk()->assertJsonPath('data.status', 'ordered');
        $this->postJson("/api/v1/inventory/purchases/{$po}/receive", [], $this->h())->assertOk()->assertJsonPath('data.status', 'received');

        return $po;
    }

    public function test_recipe_cost_and_margin_per_size(): void
    {
        $recipe = $this->getJson("/api/v1/catalog/products/{$this->latte->id}/recipe", $this->h())->assertOk()->json('data');

        // Small: 18 g × 2,000,000/kg = 36,000 + 200 ml × 400,000/L = 80,000 + one cup 20,000 = 136,000.
        $this->assertSame(136_000, $recipe['variants'][0]['cost']);
        $this->assertSame(850_000 - 136_000, $recipe['variants'][0]['margin']);
        $this->assertEqualsWithDelta(0.16, $recipe['variants'][0]['food_cost_ratio'], 0.001);
        // Large typed in litres: 0.3 L → 300 ml.
        $this->assertSame(300.0, (float) collect($recipe['variants'][1]['items'])->firstWhere('ingredient_id', $this->ing['milk'])['quantity']);
        $almond = collect($recipe['modifiers'])->firstWhere('name', 'شیر بادام');
        $this->assertSame(-200.0, (float) collect($almond['items'])->firstWhere('ingredient_id', $this->ing['milk'])['quantity']);
    }

    public function test_purchase_receive_sale_decrement_cost_snapshot_and_reversal(): void
    {
        $this->receiveOpeningStock();
        $this->assertSame(2000.0, $this->stock('beans'));
        $this->assertSame(10000.0, $this->stock('milk'));   // 10 packs of 1 L
        $this->assertSame(100.0, $this->stock('cup'));

        // Opening stock was zero, so the average cost is the purchase price.
        $beans = collect($this->getJson('/api/v1/inventory/ingredients', $this->h())->json('data'))->keyBy('id')[$this->ing['beans']];
        $this->assertSame(2_200_000, $beans['cost_per_big_unit']);
        $this->assertSame(4_400_000, $beans['stock_value']);

        // Two small lattes with almond milk and one espresso.
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->latte, 0), 2, [$this->milkOption('شیر بادام')], $auth)->assertCreated();
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth)->assertCreated();
        $order = $this->checkout($cart, [], $auth)->assertCreated()->json('data');

        $this->assertSame(2000.0 - 54, $this->stock('beans'));
        $this->assertSame(2000.0 - 400, $this->stock('almond'));
        $this->assertSame(10000.0, $this->stock('milk'));     // swapped out entirely
        $this->assertSame(97.0, $this->stock('cup'));

        // Cost frozen at sale: per latte 18 g × 2,200/g + 200 ml × 1,500/ml + cup 25,000 = 364,600.
        $latteItem = collect($order['items'])->firstWhere('product_name', 'لاته')['id'];
        $this->assertSame(2 * 364_600, $this->inTenant($this->tenant, fn () => OrderItemCost::query()->where('order_item_id', $latteItem)->value('cost')));

        // Cancelling puts it all back (once, even if repeated).
        $this->postJson("/api/v1/orders/{$order['id']}/status", ['status' => 'cancelled', 'note' => 'مشتری منصرف شد'], $this->h())->assertOk();
        $this->assertSame(2000.0, $this->stock('beans'));
        $this->assertSame(100.0, $this->stock('cup'));
        // Latte: beans, almond, cup; espresso: beans, cup (the milk swap nets to zero, so no milk moved).
        $this->assertSame(5, $this->inTenant($this->tenant, fn () => StockMovement::query()->where('order_id', $order['id'])->where('type', 'sale_reversal')->count()));
    }

    public function test_weighted_average_cost_partial_receive_and_payments(): void
    {
        $first = $this->receiveOpeningStock();
        $supplier = $this->getJson('/api/v1/inventory/suppliers', $this->h())->json('data.0.id');

        $po = $this->postJson('/api/v1/inventory/purchases', [
            'supplier_id' => $supplier, 'branch_id' => $this->branch->id,
            'items' => [['ingredient_id' => $this->ing['beans'], 'quantity' => 2, 'unit' => 'kg', 'unit_price' => 2_500_000]],
        ], $this->h())->assertCreated()->json('data');

        // Half arrives now, at a corrected price.
        $this->postJson("/api/v1/inventory/purchases/{$po['id']}/receive", ['lines' => [['item_id' => $po['items'][0]['id'], 'quantity' => 1, 'unit' => 'kg', 'unit_price' => 2_800_000]]], $this->h())
            ->assertOk()->assertJsonPath('data.status', 'ordered')->assertJsonPath('data.items.0.received_quantity', 1000);
        // (2000 g × 2,200,000 + 1000 g × 2,800,000) / 3000 g = 2,400,000 per kg.
        $beans = collect($this->getJson('/api/v1/inventory/ingredients', $this->h())->json('data'))->keyBy('id')[$this->ing['beans']];
        $this->assertSame(2_400_000, $beans['cost_per_big_unit']);
        $this->postJson("/api/v1/inventory/purchases/{$po['id']}/cancel", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'purchase_has_receipts');

        // Payments and balance owed.
        $this->postJson("/api/v1/inventory/purchases/{$first}/payments", ['amount' => '۵۰۰۰۰۰۰', 'method' => 'transfer'], $this->h())
            ->assertOk()->assertJsonPath('data.paid_total', 5_000_000)->assertJsonPath('data.balance_due', 13_900_000 - 5_000_000);
        $this->postJson("/api/v1/inventory/purchases/{$first}/payments", ['amount' => 99_000_000, 'method' => 'cash'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'overpayment');
        $this->assertSame(13_900_000 - 5_000_000 + 5_600_000, $this->getJson('/api/v1/inventory/suppliers', $this->h())->json('data.0.owed'));
        // A received order can't be edited.
        $this->putJson("/api/v1/inventory/purchases/{$first}", ['supplier_id' => $supplier, 'branch_id' => $this->branch->id, 'items' => [['ingredient_id' => $this->ing['cup'], 'quantity' => 1, 'unit_price' => 1]]], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'purchase_not_editable');
    }

    public function test_waste_count_ledger_and_low_stock_alert(): void
    {
        $this->receiveOpeningStock();

        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->ing['milk'], 'branch_id' => $this->branch->id, 'type' => 'waste', 'quantity' => '۱.۵', 'unit' => 'l', 'note' => 'ترشید'], $this->h())
            ->assertCreated()->assertJsonPath('data.quantity', -1500)->assertJsonPath('data.balance_after', 8500);
        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->ing['milk'], 'branch_id' => $this->branch->id, 'type' => 'adjustment', 'quantity' => 100], $this->h())
            ->assertUnprocessable()->assertJsonValidationErrors('note'); // an adjustment needs a reason

        // Count: 1.9 kg of beans found (book 2 kg) and 40 cups (book 100) → two count movements.
        $this->postJson('/api/v1/inventory/counts', ['branch_id' => $this->branch->id, 'lines' => [
            ['ingredient_id' => $this->ing['beans'], 'counted' => 1.9, 'unit' => 'kg'],
            ['ingredient_id' => $this->ing['cup'], 'counted' => 40],
            ['ingredient_id' => $this->ing['almond'], 'counted' => 2000],
        ]], $this->h())->assertOk()->assertJsonPath('data.adjusted', 2);
        $this->assertSame(1900.0, $this->stock('beans'));

        $ledger = $this->getJson("/api/v1/inventory/movements?ingredient_id={$this->ing['beans']}", $this->h())->assertOk()->json('data');
        $this->assertSame(['count', 'purchase'], array_column($ledger, 'type'));
        $this->assertSame(1900.0, (float) $ledger[0]['balance_after']);

        // 40 cups ≤ threshold 50 → low-stock alert and filter.
        $alert = collect($this->getJson('/api/v1/dashboard/alerts', $this->h())->json('data'))->firstWhere('type', 'low_stock');
        $this->assertSame(1, $alert['count']);
        $this->assertSame([$this->ing['cup']], array_column($this->getJson('/api/v1/inventory/ingredients?low=1', $this->h())->json('data'), 'id'));
    }

    public function test_permissions_and_ingredient_rules(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');

        $this->getJson('/api/v1/inventory/ingredients', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
        $this->getJson('/api/v1/inventory/ingredients', $this->staffHeaders($kitchen, $this->tenant))->assertOk();
        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->ing['milk'], 'branch_id' => $this->branch->id, 'type' => 'waste', 'quantity' => 1], $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();
        $this->getJson('/api/v1/inventory/purchases', $this->staffHeaders($kitchen, $this->tenant))->assertForbidden();

        // Names are unique per café; the base unit can't change; units must fit.
        $this->postJson('/api/v1/inventory/ingredients', ['name' => 'شیر', 'unit' => 'ml'], $this->h())->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/v1/inventory/ingredients/{$this->ing['milk']}", ['name' => 'شیر پرچرب', 'unit' => 'g'], $this->h())->assertOk()->assertJsonPath('data.unit', 'ml');
        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->ing['cup'], 'branch_id' => $this->branch->id, 'type' => 'waste', 'quantity' => 1, 'unit' => 'kg'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'unit_mismatch');

        // An ingredient used in a recipe can't be deleted; an unused one can.
        $this->deleteJson("/api/v1/inventory/ingredients/{$this->ing['beans']}", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ingredient_in_use');
        $spare = $this->postJson('/api/v1/inventory/ingredients', ['name' => 'دارچین', 'unit' => 'g'], $this->h())->json('data.id');
        $this->deleteJson("/api/v1/inventory/ingredients/{$spare}", [], $this->h())->assertNoContent();
    }

    public function test_food_cost_and_stock_widgets(): void
    {
        $this->receiveOpeningStock();
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->latte, 0), 1, [$this->milkOption('شیر معمولی')], $auth)->assertCreated();
        $this->checkout($cart, [], $auth)->assertCreated();

        $data = $this->getJson('/api/v1/dashboard/widgets/food_cost?range=today', $this->h())->assertOk()->json('data');
        // Small latte with normal milk: 18 g × 2,200 + 200 ml × 400 + cup 25,000 = 144,600 against 850,000.
        $this->assertSame(850_000, $data['revenue']);
        $this->assertSame(144_600, $data['cost']);
        $this->assertSame(850_000 - 144_600, $data['gross_margin']);
        $this->assertSame(1.0, (float) $data['coverage']);
        $this->assertSame('لاته', $data['top'][0]['name']);

        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->ing['cup'], 'branch_id' => $this->branch->id, 'type' => 'waste', 'quantity' => 60], $this->h())->assertCreated();
        $items = $this->getJson('/api/v1/dashboard/widgets/stock_alerts', $this->h())->assertOk()->json('data.items');
        $this->assertSame('لیوان کاغذی', $items[0]['name']);
        $this->assertSame(39.0, (float) $items[0]['quantity']);
    }
}
