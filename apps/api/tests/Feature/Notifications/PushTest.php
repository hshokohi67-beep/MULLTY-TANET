<?php

namespace Tests\Feature\Notifications;

use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Models\PushSubscription;
use App\Support\Push\WebPushCrypto;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Payments\PaymentsTestCase;

/** Browser push: customers following an order, staff devices hearing about new orders. */
final class PushTest extends PaymentsTestCase
{
    private const FCM = 'https://fcm.googleapis.com/fcm/send/device-abc';

    protected function setUp(): void
    {
        parent::setUp();
        ['private' => $key, 'public' => $public] = WebPushCrypto::newKeyPair();
        config(['webpush.public_key' => WebPushCrypto::b64($public), 'webpush.private_key' => base64_encode(WebPushCrypto::exportPrivate($key)), 'webpush.subject' => 'mailto:ops@cafeyar.ir']);
    }

    /** @return array<string, mixed> a browser subscription with real keys */
    private function browser(string $endpoint = self::FCM): array
    {
        ['public' => $public] = WebPushCrypto::newKeyPair();

        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => WebPushCrypto::b64($public), 'auth' => WebPushCrypto::b64(random_bytes(16))]];
    }

    /** A takeaway order with a signed-in customer; returns [id, tracking token]. */
    private function takeaway(): array
    {
        [, $token] = $this->customer();
        $auth = ['Authorization' => "Bearer {$token}"];
        $cart = $this->cart('takeaway', $auth);
        $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth)->assertCreated();
        $id = $this->checkout($cart, [], $auth)->assertCreated()->json('data.id');
        $tracking = $this->inTenant($this->tenant, fn () => Order::query()->findOrFail($id)->trackingToken());

        return [$id, $tracking];
    }

    private function moveTo(string $order, string $status): void
    {
        $this->postJson("/api/v1/orders/{$order}/status", ['status' => $status], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
    }

    public function test_a_customer_follows_an_order_with_its_tracking_token_and_hears_when_it_is_ready(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        [$order, $tracking] = $this->takeaway();
        $this->getJson('/api/v1/public/push/key')->assertOk()->assertJsonPath('data.public_key', config('webpush.public_key'));

        $follow = fn (array $headers, array $body = []) => $this->postJson("/api/v1/public/orders/{$order}/push", [...$this->browser(), 'url' => "/s/{$this->tenant->slug}/track/{$order}#t=x", ...$body], $this->publicHeaders($headers));
        $follow([])->assertNotFound();
        $follow(['X-Order-Token' => 'wrong'])->assertNotFound();
        $follow(['X-Order-Token' => $tracking], ['url' => 'https://evil.example/phish'])->assertUnprocessable()->assertJsonValidationErrors('url');
        $follow(['X-Order-Token' => $tracking], ['endpoint' => 'https://evil.example/push'])->assertUnprocessable()->assertJsonPath('code', 'push_unsupported');
        $follow(['X-Order-Token' => $tracking])->assertNoContent();
        $follow(['X-Order-Token' => $tracking])->assertNoContent(); // the same browser again: still one row

        $this->assertSame(1, $this->inTenant($this->tenant, fn () => PushSubscription::query()->count()));

        $this->moveTo($order, 'accepted');
        $this->moveTo($order, 'preparing'); // no push for this step
        $this->moveTo($order, 'ready');

        $sent = Http::recorded(fn (HttpRequest $r) => str_starts_with($r->url(), self::FCM));
        $this->assertCount(2, $sent);
        [$request] = $sent[1];
        $this->assertSame('aes128gcm', $request->header('Content-Encoding')[0]);
        $this->assertStringStartsWith('vapid t=', $request->header('Authorization')[0]);
        $this->assertStringNotContainsString('آماده', $request->body()); // the payload is encrypted
    }

    public function test_a_gone_subscription_is_deleted_and_push_off_means_nothing_is_stored(): void
    {
        [$order, $tracking] = $this->takeaway();
        $this->postJson("/api/v1/public/orders/{$order}/push", $this->browser(), $this->publicHeaders(['X-Order-Token' => $tracking]))->assertNoContent();

        Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
        $this->moveTo($order, 'accepted');
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => PushSubscription::query()->count()));

        config(['webpush.public_key' => '', 'webpush.private_key' => '']);
        $this->getJson('/api/v1/public/push/key')->assertOk()->assertJsonPath('data.public_key', null);
        $this->postJson("/api/v1/public/orders/{$order}/push", $this->browser(), $this->publicHeaders(['X-Order-Token' => $tracking]))
            ->assertUnprocessable()->assertJsonPath('code', 'push_disabled');
    }

    public function test_staff_devices_hear_about_new_orders_only_while_they_can_see_orders(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $h = $this->staffHeaders($this->owner, $this->tenant);
        $device = $this->browser();
        $this->postJson('/api/v1/push/subscription/status', ['endpoint' => $device['endpoint']], $h)->assertOk()->assertJsonPath('data.subscribed', false);
        $this->postJson('/api/v1/push/subscription', $device, $h)->assertNoContent();
        $this->postJson('/api/v1/push/subscription/status', ['endpoint' => $device['endpoint']], $h)->assertOk()->assertJsonPath('data.subscribed', true);

        // A cashier's device too; then the cashier is disabled: no alerts, no new subscriptions.
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->postJson('/api/v1/push/subscription', $this->browser(self::FCM.'-cashier'), $this->staffHeaders($cashier, $this->tenant))->assertNoContent();
        $this->inTenant($this->tenant, fn () => TenantUser::query()->where('user_id', $cashier->id)->update(['status' => TenantUser::STATUS_DISABLED]));
        $this->postJson('/api/v1/push/subscription', $this->browser(), $this->staffHeaders($cashier, $this->tenant))->assertForbidden();

        $this->quickQrOrder();
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => str_starts_with($r->url(), self::FCM)));

        $this->postJson('/api/v1/push/subscription/remove', ['endpoint' => $device['endpoint']], $h)->assertNoContent();
        $this->quickQrOrder();
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => str_starts_with($r->url(), self::FCM)));
    }
}
