<?php

namespace Tests\Feature\Commerce;

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

final class OrderHistoryTest extends CommerceTestCase
{
    private function staff(string $uri): TestResponse
    {
        return $this->getJson($uri, $this->staffHeaders($this->owner, $this->tenant));
    }

    private function setStatus(string $order, array $steps): void
    {
        foreach ($steps as $status) {
            $this->postJson("/api/v1/orders/{$order}/status", ['status' => $status, 'note' => 'تست'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        }
    }

    public function test_live_version_changes_only_when_the_board_changes(): void
    {
        $first = $this->staff('/api/v1/orders/live-version')->assertOk()->json('data.version');
        $this->getJson('/api/v1/orders/live-version', [...$this->staffHeaders($this->owner, $this->tenant), 'If-None-Match' => '"'.$first.'"'])->assertStatus(304);

        $this->quickQrOrder();
        $second = $this->staff('/api/v1/orders/live-version')->json('data.version');
        $this->assertNotSame($first, $second);

        // A waiter call changes it too.
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $this->publicHeaders(['X-Table-Session' => $this->joinTable()]))->assertCreated();
        $this->assertNotSame($second, $this->staff('/api/v1/orders/live-version')->json('data.version'));
    }

    public function test_history_filters_search_and_summary(): void
    {
        // Mid-day, so "+10 minutes" never crosses midnight into another business date.
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Tehran'));
        $done = $this->quickQrOrder()->json('data.id');           // #1, 650,000
        $this->setStatus($done, ['accepted', 'preparing', 'ready', 'completed']);
        $cancelled = $this->quickQrOrder()->json('data.id');      // #2
        $this->setStatus($cancelled, ['cancelled']);
        $open = $this->quickQrOrder()->json('data.id');           // #3, still open

        [, $token] = $this->customer('+989121234567');
        $cart = $this->cart('takeaway', ['Authorization' => "Bearer {$token}"]);
        $this->addItem($cart, $this->variant($this->espresso), 2, headers: ['Authorization' => "Bearer {$token}"]);
        $takeaway = $this->checkout($cart, headers: ['Authorization' => "Bearer {$token}"])->json('data.id'); // #4, 1,300,000

        $closed = array_column($this->staff('/api/v1/orders?status=closed')->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$done, $cancelled], $closed);
        $this->assertSame([$open, $takeaway], array_reverse(array_column(array_filter($this->staff('/api/v1/orders?status=open')->json('data'), fn ($o) => in_array($o['id'], [$open, $takeaway], true)), 'id')));

        // Search: daily number (Persian digits too), phone fragment, name.
        $this->assertSame([$cancelled], array_column($this->staff('/api/v1/orders?q='.urlencode('#۲'))->json('data'), 'id'));
        $this->assertSame([$takeaway], array_column($this->staff('/api/v1/orders?q=1234567')->json('data'), 'id'));
        $this->assertSame([$takeaway], array_column($this->staff('/api/v1/orders?q='.urlencode('مشتری'))->json('data'), 'id'));
        $this->assertSame([$takeaway], array_column($this->staff('/api/v1/orders?type=takeaway')->json('data'), 'id'));

        $today = now('Asia/Tehran')->toDateString();
        $this->staff("/api/v1/orders/summary?from={$today}&to={$today}")->assertOk()
            ->assertJsonPath('data.orders', 4)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.cancelled', 1)
            ->assertJsonPath('data.revenue', 650_000 * 2 + 1_300_000)
            ->assertJsonPath('data.average', 866_670); // 2,600,000 / 3, rounded to a whole toman

        // "Just finished" feed for the ready notification.
        $this->assertSame([$done], array_column($this->staff('/api/v1/orders?status=completed&completed_within=3')->json('data'), 'id'));
        $this->travel(10)->minutes();
        $this->assertSame([], $this->staff('/api/v1/orders?status=completed&completed_within=3')->json('data'));

        $yesterday = now('Asia/Tehran')->subDay()->toDateString();
        $this->assertSame([], $this->staff("/api/v1/orders?from={$yesterday}&to={$yesterday}")->json('data'));
        $this->staff("/api/v1/orders?from={$today}&to={$yesterday}")->assertJsonValidationErrors('to');
    }
}
