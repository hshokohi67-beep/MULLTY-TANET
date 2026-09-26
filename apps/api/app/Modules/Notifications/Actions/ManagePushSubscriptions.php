<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Exceptions\PushException;
use App\Modules\Notifications\Models\PushSubscription;
use App\Support\Push\WebPushSender;

/**
 * Stores a browser's subscription: a customer following one order, or a staff member's device.
 * The same browser subscribing again updates its row instead of adding one.
 */
final class ManagePushSubscriptions
{
    /** @param  array{endpoint: string, p256dh: string, auth: string}  $keys */
    public function forOrder(Order $order, array $keys, ?string $url): PushSubscription
    {
        return $this->save('customer', null, $order->id, $keys, $url);
    }

    /** @param  array{endpoint: string, p256dh: string, auth: string}  $keys */
    public function forStaff(User $user, array $keys): PushSubscription
    {
        return $this->save('staff', (string) $user->getKey(), null, $keys, '/dashboard/orders');
    }

    public function removeStaff(User $user, string $endpoint): void
    {
        PushSubscription::query()->where('audience', 'staff')->where('user_id', $user->getKey())
            ->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete();
    }

    public function staffHasDevice(User $user, string $endpoint): bool
    {
        return PushSubscription::query()->where('audience', 'staff')->where('user_id', $user->getKey())
            ->where('endpoint_hash', PushSubscription::hashOf($endpoint))->exists();
    }

    /** @param  array{endpoint: string, p256dh: string, auth: string}  $keys */
    private function save(string $audience, ?string $userId, ?string $orderId, array $keys, ?string $url): PushSubscription
    {
        if (! WebPushSender::enabled()) {
            throw PushException::disabled();
        }
        if (! WebPushSender::allowedEndpoint($keys['endpoint'])) {
            throw PushException::unsupported();
        }

        $sub = PushSubscription::query()
            ->where('endpoint_hash', PushSubscription::hashOf($keys['endpoint']))
            ->where('audience', $audience)
            ->when($orderId !== null, fn ($q) => $q->where('order_id', $orderId), fn ($q) => $q->whereNull('order_id'))
            ->first() ?? new PushSubscription;

        $sub->fill([
            'audience' => $audience,
            'user_id' => $userId,
            'order_id' => $orderId,
            'endpoint_hash' => PushSubscription::hashOf($keys['endpoint']),
            'endpoint' => $keys['endpoint'],
            'p256dh' => $keys['p256dh'],
            'auth' => $keys['auth'],
            'url' => $url,
            'failures' => 0,
        ])->save();

        return $sub;
    }
}
