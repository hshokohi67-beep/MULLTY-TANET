<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\DomainType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $domain
 * @property DomainType $type
 * @property bool $is_primary
 */
#[Fillable(['domain', 'type', 'is_primary', 'verified_at'])]
class TenantDomain extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
