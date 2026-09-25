<?php

namespace Tests\Feature\Insights;

use Tests\Feature\Commerce\CommerceTestCase;

final class DashboardExperienceTest extends CommerceTestCase
{
    public function test_search_finds_products_orders_and_customers_with_persian_input(): void
    {
        [, $token] = $this->customer('+989121234567');
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth);
        $this->checkout($cart, ['contact_name' => 'سارا'], $auth)->assertCreated();
        $headers = $this->staffHeaders($this->owner, $this->tenant);

        $groups = collect($this->getJson('/api/v1/dashboard/search?q='.urlencode('لاته'), $headers)->assertOk()->json('data'))->keyBy('group');
        $this->assertSame('لاته', $groups['products']['items'][0]['title']);
        $this->assertStringStartsWith('/dashboard/menu/', $groups['products']['items'][0]['href']);

        // Persian digits: order #1 and the customer's phone.
        $groups = collect($this->getJson('/api/v1/dashboard/search?q='.urlencode('۱'), $headers)->json('data'))->keyBy('group');
        $this->assertStringStartsWith('#۱', $groups['orders']['items'][0]['title']);
        $groups = collect($this->getJson('/api/v1/dashboard/search?q='.urlencode('۰۹۱۲۱۲۳۴۵'), $headers)->json('data'))->keyBy('group');
        $this->assertSame('۰۹۱۲۱۲۳۴۵۶۷', $groups['customers']['items'][0]['subtitle']);

        $this->getJson('/api/v1/dashboard/search', $headers)->assertUnprocessable();
    }

    public function test_search_groups_follow_permissions(): void
    {
        $this->customer('+989121234567');
        $kitchen = $this->addMember($this->tenant, $this->owner, 'kitchen');

        $groups = collect($this->getJson('/api/v1/dashboard/search?q='.urlencode('۰۹۱۲'), $this->staffHeaders($kitchen, $this->tenant))->assertOk()->json('data'))->pluck('group')->all();
        $this->assertNotContains('customers', $groups); // the kitchen can't look up customers
    }

    public function test_alerts_endpoint_and_sold_out_alert(): void
    {
        $headers = $this->staffHeaders($this->owner, $this->tenant);
        $this->assertSame([], collect($this->getJson('/api/v1/dashboard/alerts', $headers)->assertOk()->json('data'))->where('type', 'sold_out')->values()->all());

        $this->putJson("/api/v1/catalog/products/{$this->latte->id}/availability", ['branch_id' => $this->branch->id, 'status' => 'sold_out'], $headers)->assertOk();

        $alert = collect($this->getJson('/api/v1/dashboard/alerts', $headers)->json('data'))->firstWhere('type', 'sold_out');
        $this->assertSame(1, $alert['count']);
        $this->assertSame('/dashboard/menu?availability=sold_out', $alert['href']);
    }

    public function test_setup_progress_reflects_data_and_optional_steps_can_be_skipped(): void
    {
        $headers = $this->staffHeaders($this->owner, $this->tenant);
        $setup = $this->getJson('/api/v1/dashboard/setup', $headers)->assertOk()->json('data');
        $steps = collect($setup['steps'])->keyBy('key');

        $this->assertTrue($steps['menu']['done']);          // the test café has products
        $this->assertTrue($steps['delivery']['done']);      // and a delivery zone
        $this->assertFalse($steps['logo']['done']);
        $this->assertFalse($steps['story']['done']);
        $this->assertTrue($steps['menu']['essential']);

        $after = $this->postJson('/api/v1/dashboard/setup/skip', ['step' => 'story'], $headers)->assertOk()->json('data');
        $this->assertNotContains('story', collect($after['steps'])->pluck('key')->all());
        $this->assertSame($setup['total'] - 1, $after['total']);

        $this->postJson('/api/v1/dashboard/setup/skip', ['step' => 'menu'], $headers)->assertUnprocessable();
        $this->postJson('/api/v1/dashboard/setup/skip', ['step' => 'story', 'skip' => false], $headers)->assertOk()
            ->assertJsonPath('data.total', $setup['total']);

        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->postJson('/api/v1/dashboard/setup/skip', ['step' => 'story'], $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }
}
