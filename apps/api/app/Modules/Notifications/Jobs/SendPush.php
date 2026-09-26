<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Core\Models\Tenant;
use App\Modules\Notifications\Models\PushSubscription;
use App\Support\Push\WebPushSender;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One notification to one subscription, queued after commit (never inside an order transaction).
 * A subscription the push service calls gone (404/410), or that keeps failing, is deleted.
 */
final class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    /** @param  array{title: string, body: string, tag: string, url?: string}  $message  (url: else the subscription's own) */
    public function __construct(public readonly string $tenantId, public readonly string $subscriptionId, public readonly array $message)
    {
        $this->afterCommit = true;
    }

    public function handle(TenantContext $context, WebPushSender $sender): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $context->runAs($tenant, function () use ($sender): void {
            $sub = PushSubscription::query()->find($this->subscriptionId);
            if ($sub === null) {
                return;
            }

            $result = $sender->send($sub->endpoint, $sub->p256dh, $sub->auth, [...$this->message, 'url' => $this->message['url'] ?? $sub->url ?? '/']);

            match ($result->outcome) {
                'sent' => $sub->forceFill(['failures' => 0, 'last_sent_at' => now()])->save(),
                'gone' => $sub->delete(),
                'failed' => $sub->failures + 1 >= PushSubscription::MAX_FAILURES ? $sub->delete() : $sub->forceFill(['failures' => $sub->failures + 1])->save(),
                default => null,
            };
        });
    }
}
