<?php

namespace App\Modules\Notifications\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser that asked for push notifications. Every browser-provided value is stored encrypted;
 * `endpoint_hash` finds the same browser again without decrypting.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $audience customer|staff
 * @property ?string $user_id
 * @property ?string $order_id
 * @property string $endpoint_hash
 * @property string $endpoint
 * @property string $p256dh
 * @property string $auth
 * @property ?string $url
 * @property int $failures
 * @property ?Carbon $last_sent_at
 */
#[Fillable(['audience', 'user_id', 'order_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'url', 'failures', 'last_sent_at'])]
class PushSubscription extends Model
{
    use BelongsToTenant, HasUlids;

    /** Consecutive failures after which a subscription is dropped. */
    public const MAX_FAILURES = 5;

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'p256dh' => 'encrypted',
            'auth' => 'encrypted',
            'url' => 'encrypted',
            'failures' => 'integer',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function hashOf(string $endpoint): string
    {
        return hash_hmac('sha256', $endpoint, (string) config('app.key'));
    }
}
