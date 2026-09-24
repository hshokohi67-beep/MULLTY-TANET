<?php

namespace App\Modules\Kitchen\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Tenancy\TenantBoundTokenable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A paired kitchen tablet. Replaces the legacy shared PIN: each device has its own revocable,
 * tenant-bound token and every action it takes is attributed to it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $branch_id
 * @property ?string $station_id
 * @property string $name
 * @property ?string $pairing_code_hash
 * @property ?Carbon $pairing_expires_at
 * @property ?Carbon $paired_at
 * @property ?Carbon $last_seen_at
 * @property ?Carbon $revoked_at
 * @property Branch $branch
 * @property ?KitchenStation $station
 */
#[Fillable(['branch_id', 'station_id', 'name'])]
#[Hidden(['pairing_code_hash'])]
class KitchenDevice extends Model implements AuthenticatableContract, TenantBoundTokenable
{
    use Authenticatable, BelongsToTenant, HasApiTokens, HasUlids;

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'datetime', 'paired_at' => 'datetime', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function tokenTenantId(): string
    {
        return $this->tenant_id;
    }

    public function tokenIsActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function getAuthPassword(): string
    {
        return ''; // devices authenticate with their token only
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<KitchenStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }
}
