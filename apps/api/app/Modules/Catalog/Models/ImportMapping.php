<?php

namespace App\Modules\Catalog\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Remembers which external record became which of ours, so imports are re-runnable.
 *
 * @property string $target_id
 */
#[Fillable(['source', 'source_type', 'source_id', 'target_id'])]
class ImportMapping extends Model
{
    use BelongsToTenant, HasUlids;
}
