<?php

namespace App\Modules\Messaging\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One SMS the café sent (or tried to). Append-only; pruned after 180 days.
 *
 * @property string $id
 * @property string $kind order_ready | order_sent | birthday | daily_report | campaign | test
 * @property ?string $campaign_id
 * @property string $recipient E.164
 * @property string $body
 * @property int $parts
 * @property string $status sent | failed | skipped
 * @property ?string $error
 * @property ?string $provider
 * @property ?string $provider_ref
 * @property Carbon $created_at
 */
#[Fillable(['kind', 'campaign_id', 'recipient', 'body', 'parts', 'status', 'error', 'provider', 'provider_ref', 'created_at'])]
class SmsLog extends Model
{
    use BelongsToTenant, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['parts' => 'integer', 'created_at' => 'datetime'];
    }
}
