<?php

namespace App\Modules\Insights\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property list<array{key: string, size: string}> $widgets
 */
#[Fillable(['user_id', 'widgets'])]
class DashboardLayout extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['widgets' => 'array'];
    }
}
