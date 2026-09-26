<?php

namespace App\Modules\Messaging\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A marketing SMS to the café's opted-in customers. draft → scheduled → sending → done
 * (or cancelled before it finishes). Sent in chunks by `sms:campaigns`, resuming from `cursor`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $body
 * @property array{tier_id?: ?string, birth_month?: ?int, inactive_days?: ?int, has_ordered?: bool} $audience
 * @property string $status
 * @property ?Carbon $scheduled_at
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property int $recipients
 * @property int $sent
 * @property int $failed
 * @property ?string $cursor
 * @property ?string $created_by
 * @property Carbon $created_at
 */
#[Fillable(['name', 'body', 'audience', 'status', 'scheduled_at', 'started_at', 'finished_at', 'recipients', 'sent', 'failed', 'cursor', 'created_by'])]
class SmsCampaign extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'audience' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
            'recipients' => 'integer', 'sent' => 'integer', 'failed' => 'integer',
        ];
    }
}
