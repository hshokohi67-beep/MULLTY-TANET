<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Support\PermissionCatalog;
use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Global permission catalogue row, synced from {@see PermissionCatalog}.
 *
 * @property string $id
 * @property string $key
 * @property string $group
 */
#[Fillable(['key', 'group'])]
class Permission extends Model
{
    use HasUlids, StoresDatesInUtc;
}
