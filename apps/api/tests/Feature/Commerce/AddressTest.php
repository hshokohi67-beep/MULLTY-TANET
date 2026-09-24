<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Support\Geo;

final class AddressTest extends CommerceTestCase
{
    public function test_addresses_are_private_to_their_customer(): void
    {
        [, $alice] = $this->customer('+989121111111');
        [, $bob] = $this->customer('+989122222222');

        $id = $this->postJson('/api/v1/customer/addresses', [
            'title' => 'خانه',
            'city' => 'تهران',
            'address' => 'خیابان ولیعصر',
            'postal_code' => '۱۹۹۶۸۳۵۱۱۱',
            'building_number' => '۱۲',
            'recipient_phone' => '۰۹۳۵۱۲۳۴۵۶۷',
        ], $this->publicHeaders(['Authorization' => "Bearer {$alice}"]))
            ->assertCreated()
            ->assertJsonPath('data.postal_code', '1996835111')
            ->assertJsonPath('data.recipient_phone', '09351234567')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.has_location', false)
            ->json('data.id');

        $bobHeaders = $this->publicHeaders(['Authorization' => "Bearer {$bob}"]);
        $this->assertSame([], $this->getJson('/api/v1/customer/addresses', $bobHeaders)->json('data'));
        $this->putJson("/api/v1/customer/addresses/{$id}", ['title' => 'x', 'city' => 'x', 'address' => 'x'], $bobHeaders)->assertNotFound();
        $this->deleteJson("/api/v1/customer/addresses/{$id}", [], $bobHeaders)->assertNotFound();
    }

    public function test_one_default_address(): void
    {
        [, $token] = $this->customer();
        $h = $this->publicHeaders(['Authorization' => "Bearer {$token}"]);
        $first = $this->postJson('/api/v1/customer/addresses', ['title' => 'خانه', 'city' => 'تهران', 'address' => 'a'], $h)->json('data.id');
        $second = $this->postJson('/api/v1/customer/addresses', ['title' => 'کار', 'city' => 'تهران', 'address' => 'b', 'is_default' => true], $h)->json('data.id');

        $list = collect($this->getJson('/api/v1/customer/addresses', $h)->json('data'))->keyBy('id');
        $this->assertTrue($list[$second]['is_default']);
        $this->assertFalse($list[$first]['is_default']);

        $this->deleteJson("/api/v1/customer/addresses/{$second}", [], $h)->assertNoContent();
        $this->assertTrue($this->getJson('/api/v1/customer/addresses', $h)->json('data.0.is_default'));
    }

    public function test_staff_tokens_cannot_use_customer_endpoints(): void
    {
        $this->getJson('/api/v1/customer/addresses', $this->staffHeaders($this->owner, $this->tenant))->assertForbidden();
    }

    public function test_haversine_distance(): void
    {
        // Vanak square → Tajrish square ≈ 5.6 km.
        $this->assertEqualsWithDelta(5_600, Geo::distanceMeters(35.7575, 51.4099, 35.8047, 51.4336), 250);
        $this->assertSame(0, Geo::distanceMeters(35.7, 51.4, 35.7, 51.4));
    }

    public function test_staff_can_check_whether_a_point_is_deliverable(): void
    {
        $h = $this->staffHeaders($this->owner, $this->tenant);

        $this->postJson('/api/v1/delivery-zones/check', ['branch_id' => $this->branch->id, 'latitude' => 35.7610, 'longitude' => 51.4120], $h)
            ->assertOk()->assertJsonPath('data.zone_name', 'تا ۳ کیلومتر');
        $this->postJson('/api/v1/delivery-zones/check', ['branch_id' => $this->branch->id, 'latitude' => 35.6892, 'longitude' => 51.3890], $h)
            ->assertUnprocessable()->assertJsonPath('code', 'out_of_delivery_area');
    }
}
