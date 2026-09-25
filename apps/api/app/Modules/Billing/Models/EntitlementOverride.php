<?php

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A platform grant for one tenant: the value replaces the plan's (bool, int, or null = unlimited).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $feature
 * @property bool|int|null $value
 * @property string $reason
 * @property ?Carbon $expires_at
 * @property ?string $granted_by
 */
#[Fillable(['feature', 'value', 'reason', 'expires_at', 'granted_by'])]
class EntitlementOverride extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['value' => 'json', 'expires_at' => 'datetime'];
    }
}
