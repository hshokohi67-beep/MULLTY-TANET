<?php

namespace Tests\Feature\Kitchen;

use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Loyalty\ClubTestCase;

/**
 * The commerce fixture plus two stations in the main branch: «بار قهوه» ("coffee bar", default)
 * and «آشپزخانه» ("kitchen", late after 12 minutes), with the latte routed to the bar explicitly.
 */
abstract class KitchenTestCase extends ClubTestCase
{
    protected KitchenStation $bar;

    protected KitchenStation $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        $headers = $this->staffHeaders($this->owner, $this->tenant);
        $barId = $this->postJson('/api/v1/kitchen/stations', ['branch_id' => $this->branch->id, 'name' => 'بار قهوه'], $headers)->assertCreated()->json('data.id');
        $kitchenId = $this->postJson('/api/v1/kitchen/stations', ['branch_id' => $this->branch->id, 'name' => 'آشپزخانه', 'late_after_minutes' => 12, 'sort' => 1], $headers)->assertCreated()->json('data.id');
        $this->bar = $this->inTenant($this->tenant, fn () => KitchenStation::query()->findOrFail($barId));
        $this->kitchen = $this->inTenant($this->tenant, fn () => KitchenStation::query()->findOrFail($kitchenId));
        $this->putJson("/api/v1/kitchen/stations/{$this->kitchen->id}/products", ['product_ids' => [$this->espresso->id]], $headers)->assertNoContent();
    }

    /** @return array<string, string> */
    protected function kds(?string $token = null): array
    {
        return $token === null
            ? $this->staffHeaders($this->owner, $this->tenant)
            : ['X-Tenant' => $this->tenant->slug, 'Accept' => 'application/json', 'Authorization' => "Bearer {$token}"];
    }

    /** Pairs a new device and returns its token. */
    protected function pairDevice(?string $stationId = null, string $name = 'تبلت بار'): string
    {
        $code = $this->postJson('/api/v1/kitchen/devices', ['name' => $name, 'branch_id' => $this->branch->id, 'station_id' => $stationId], $this->staffHeaders($this->owner, $this->tenant))
            ->assertCreated()->json('pairing_code');

        return $this->postJson('/api/v1/public/kds/pair', ['code' => $code], $this->publicHeaders())->assertOk()->json('token');
    }

    protected function board(?string $token = null, string $query = ''): TestResponse
    {
        return $this->getJson('/api/v1/kds/board'.$query, $this->kds($token));
    }

    /** @return list<KitchenItem> */
    protected function items(string $orderId): array
    {
        return $this->inTenant($this->tenant, fn () => KitchenItem::query()->where('order_id', $orderId)->orderBy('created_at')->orderBy('id')->get()->all());
    }

    protected function act(string $itemId, string $action, ?string $token = null): TestResponse
    {
        return $this->postJson("/api/v1/kds/items/{$itemId}/{$action}", [], $this->kds($token));
    }

    /** A QR order with a latte (bar) and two espressos (kitchen). */
    protected function mixedOrder(): string
    {
        $cart = $this->cart('qr_table', ['X-Table-Session' => $this->joinTable()]);
        $this->addItem($cart, $this->variant($this->latte), 1, [$this->milkOption('شیر بادام')])->assertCreated();
        $this->addItem($cart, $this->variant($this->espresso), 2)->assertCreated();

        return $this->checkout($cart, ['note' => 'فوری'])->assertCreated()->json('data.id');
    }
}
