<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property ?string $phone
 * @property ?string $notes
 * @property bool $is_active
 */
#[Fillable(['name', 'phone', 'notes', 'is_active'])]
class Supplier extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
