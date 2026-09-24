<?php

namespace App\Modules\Commerce\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Only the SHA-256 of the printed token is stored.
 *
 * @property string $id
 * @property string $table_id
 * @property string $token_hash
 * @property string $token_hint
 * @property bool $is_active
 * @property ?Carbon $revoked_at
 * @property ?Carbon $created_at
 * @property RestaurantTable $table
 */
#[Fillable(['table_id', 'token_hash', 'token_hint', 'is_active', 'revoked_at'])]
#[Hidden(['token_hash'])]
class TableQrCode extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<RestaurantTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }
}
