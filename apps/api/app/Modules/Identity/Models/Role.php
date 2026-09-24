<?php

namespace App\Modules\Identity\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property bool $is_system
 */
#[Fillable(['key', 'name', 'is_system'])]
class Role extends Model
{
    use BelongsToTenant, HasUlids;

    public const OWNER = 'owner';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER;
    }
}
