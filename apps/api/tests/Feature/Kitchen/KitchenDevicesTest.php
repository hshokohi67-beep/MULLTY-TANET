<?php

namespace Tests\Feature\Kitchen;

use App\Modules\Core\Models\Branch;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Models\KitchenEvent;
use App\Modules\Kitchen\Models\KitchenStation;

final class KitchenDevicesTest extends KitchenTestCase
{
    public function test_pairing_codes_are_single_use_short_lived_and_stored_hashed(): void
    {
        $response = $this->postJson('/api/v1/kitchen/devices', ['name' => 'تبلت بار', 'branch_id' => $this->branch->id], $this->kds())->assertCreated()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('expires_in', 600);
        $code = $response->json('pairing_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $stored = $this->inTenant($this->tenant, fn () => KitchenDevice::query()->firstOrFail());
        $this->assertNotSame($code, $stored->pairing_code_hash);
        $this->assertStringNotContainsString($code, (string) $stored->pairing_code_hash);

        // Persian digits and spaces are fine.
        $persian = strtr(substr($code, 0, 3).' '.substr($code, 3), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        $this->postJson('/api/v1/public/kds/pair', ['code' => $persian], $this->publicHeaders())->assertOk()->assertJsonPath('device.name', 'تبلت بار');
        $this->postJson('/api/v1/public/kds/pair', ['code' => $code], $this->publicHeaders())->assertUnprocessable()->assertJsonPath('code', 'kitchen_pairing_invalid');

        // Expired codes don't work.
        $second = $this->postJson('/api/v1/kitchen/devices', ['name' => 'دوم', 'branch_id' => $this->branch->id], $this->kds())->json('pairing_code');
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/public/kds/pair', ['code' => $second], $this->publicHeaders())->assertJsonPath('code', 'kitchen_pairing_invalid');
    }

    public function test_wrong_codes_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/public/kds/pair', ['code' => '000000'], $this->publicHeaders())->assertUnprocessable();
        }

        $this->postJson('/api/v1/public/kds/pair', ['code' => '000000'], $this->publicHeaders())->assertStatus(429);
    }

    public function test_a_device_works_the_board_is_attributed_and_can_reach_nothing_else(): void
    {
        $token = $this->pairDevice();
        $order = $this->mixedOrder();
        [$latte] = $this->items($order);

        $this->getJson('/api/v1/kds/me', $this->kds($token))->assertOk()->assertJsonPath('data.actor.type', 'device')->assertJsonPath('data.branches.0.stations.0.name', 'بار قهوه');
        $this->board($token)->assertOk()->assertJsonCount(1, 'data.orders');
        $this->act($latte->id, 'start', $token)->assertOk();

        $event = $this->inTenant($this->tenant, fn () => KitchenEvent::query()->where('type', 'started')->firstOrFail());
        $device = $this->inTenant($this->tenant, fn () => KitchenDevice::query()->firstOrFail());
        $this->assertSame(['device', $device->id], [$event->actor_type, $event->actor_id]);
        $this->assertNotNull($device->last_seen_at);

        // A device token is not a staff token.
        $this->getJson('/api/v1/orders', $this->kds($token))->assertForbidden();
        $this->getJson('/api/v1/kitchen/setup', $this->kds($token))->assertForbidden();
        $this->getJson('/api/v1/customer/club', $this->kds($token))->assertForbidden();
    }

    public function test_a_station_device_sees_and_touches_only_its_station(): void
    {
        $token = $this->pairDevice($this->bar->id);
        $order = $this->mixedOrder();
        [$latte, $espresso] = $this->items($order);

        $orders = $this->board($token)->json('data.orders');
        $this->assertCount(1, $orders[0]['items']);
        $this->assertSame('لاته', $orders[0]['items'][0]['name']);

        $this->act($espresso->id, 'start', $token)->assertNotFound();
        $this->postJson("/api/v1/kds/orders/{$order}/bump", ['station_id' => $this->kitchen->id], $this->kds($token))->assertNotFound();
        $this->act($latte->id, 'ready', $token)->assertOk();
    }

    public function test_devices_are_branch_bound_and_revocable(): void
    {
        $other = $this->inTenant($this->tenant, function (): Branch {
            $branch = Branch::query()->create(['name' => 'شعبه ونک', 'slug' => 'vanak']);
            KitchenStation::query()->create(['branch_id' => $branch->id, 'name' => 'بار ونک', 'is_default' => true]);

            return $branch;
        });
        $token = $this->pairDevice();
        $this->mixedOrder();

        $this->board($token, "?branch_id={$other->id}")->assertOk()->assertJsonPath('data.branch_id', $this->branch->id); // ignored: bound to its own branch

        $device = $this->inTenant($this->tenant, fn () => KitchenDevice::query()->firstOrFail());
        $this->postJson("/api/v1/kitchen/devices/{$device->id}/revoke", [], $this->kds())->assertOk()->assertJsonPath('data.status', 'revoked');
        $this->board($token)->assertUnauthorized();

        // Re-pairing issues a fresh code and a fresh token.
        $code = $this->postJson("/api/v1/kitchen/devices/{$device->id}/repair", [], $this->kds())->assertOk()->json('pairing_code');
        $new = $this->postJson('/api/v1/public/kds/pair', ['code' => $code], $this->publicHeaders())->assertOk()->json('token');
        $this->board($new)->assertOk();
    }

    public function test_permissions_and_station_rules(): void
    {
        $kitchenStaff = $this->addMember($this->tenant, $this->owner, 'kitchen');
        $this->getJson('/api/v1/kds/board', $this->staffHeaders($kitchenStaff, $this->tenant))->assertOk();
        $this->getJson('/api/v1/kitchen/setup', $this->staffHeaders($kitchenStaff, $this->tenant))->assertForbidden();

        $this->getJson('/api/v1/kitchen/setup', $this->kds())->assertOk()
            ->assertJsonPath('data.stations.0.is_default', true)
            ->assertJsonPath('data.stations.1.product_ids.0', $this->espresso->id);

        // Making the kitchen the default un-defaults the bar.
        $this->putJson("/api/v1/kitchen/stations/{$this->kitchen->id}", ['name' => 'آشپزخانه', 'is_default' => true], $this->kds())->assertOk();
        $this->assertFalse($this->bar->fresh()?->is_default);

        // A used station can't be deleted, only deactivated.
        $this->mixedOrder();
        $this->deleteJson("/api/v1/kitchen/stations/{$this->kitchen->id}", [], $this->kds())->assertUnprocessable()->assertJsonPath('code', 'kitchen_station_in_use');
        $spare = $this->postJson('/api/v1/kitchen/stations', ['branch_id' => $this->branch->id, 'name' => 'دسر'], $this->kds())->json('data.id');
        $this->deleteJson("/api/v1/kitchen/stations/{$spare}", [], $this->kds())->assertNoContent();
    }
}
