<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\PermissionCatalog;
use App\Modules\Identity\Support\PermissionResolver;
use App\Modules\Notifications\Jobs\SendPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Support\Localization\PersianNumber;
use App\Support\Push\WebPushSender;
use App\Support\Tenancy\TenantContext;

/**
 * Decides who hears about an order and what they read. Messages carry the order number and the
 * café name only (never the customer's name or items), because lock screens are public.
 */
final class PushNotifier
{
    /** Order types whose customer waits elsewhere for "ready" (at a table the waiter brings it). */
    private const READY_TYPES = [OrderType::Takeaway, OrderType::Online, OrderType::Phone];

    public function orderStatus(Order $order, OrderStatus $to): void
    {
        if (! WebPushSender::enabled()) {
            return;
        }
        $cafe = app(TenantContext::class)->require()->name;
        $number = PersianNumber::toPersian((string) $order->daily_number);

        [$title, $body] = match (true) {
            $to === OrderStatus::Accepted => ["سفارش #{$number} تأیید شد", "{$cafe} سفارش شما را قبول کرد."],
            $to === OrderStatus::Ready && in_array($order->type, self::READY_TYPES, true) => ["سفارش #{$number} آماده است", "{$cafe}: سفارش‌تان آماده‌ی تحویل است."],
            $to === OrderStatus::OutForDelivery => ["سفارش #{$number} در راه است", "{$cafe}: سفارش‌تان با پیک ارسال شد."],
            $to === OrderStatus::Rejected, $to === OrderStatus::Cancelled => ["سفارش #{$number} لغو شد", "{$cafe}: برای جزئیات صفحه‌ی سفارش را ببینید."],
            default => [null, null],
        };
        if ($title === null || $body === null) {
            return;
        }

        foreach (PushSubscription::query()->where('audience', 'customer')->where('order_id', $order->id)->pluck('id') as $id) {
            SendPush::dispatch((string) $order->tenant_id, (string) $id, ['title' => $title, 'body' => $body, 'tag' => 'order-'.$order->id]);
        }
    }

    /** «سفارش تازه» to every staff device whose owner can still see orders here. */
    public function newOrder(Order $order): void
    {
        if (! WebPushSender::enabled()) {
            return;
        }
        $subs = PushSubscription::query()->where('audience', 'staff')->whereNotNull('user_id')->get(['id', 'user_id']);
        if ($subs->isEmpty()) {
            return; // most cafés: nothing to do, not even a query for the branch
        }
        $tenant = app(TenantContext::class)->require();
        $resolver = app(PermissionResolver::class);
        $number = PersianNumber::toPersian((string) $order->daily_number);
        $branch = $order->branch->name;

        foreach ($subs->groupBy('user_id') as $userId => $devices) {
            $user = User::query()->find($userId);
            if ($user === null || ! $resolver->allows($user, $tenant, PermissionCatalog::ORDERS_VIEW)) {
                continue;
            }
            foreach ($devices as $device) {
                SendPush::dispatch((string) $order->tenant_id, (string) $device->id, [
                    'title' => "سفارش تازه #{$number}",
                    'body' => $branch ? "شعبه {$branch} • در صف سفارش‌ها" : 'در صف سفارش‌ها',
                    'tag' => 'new-'.$order->id,
                    'url' => '/dashboard/orders/'.$order->id,
                ]);
            }
        }
    }
}
