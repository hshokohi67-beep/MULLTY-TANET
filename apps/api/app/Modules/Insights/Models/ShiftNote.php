<?php

namespace App\Modules\Insights\Models;

use App\Modules\Identity\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A short handover note from one shift to the next.
 *
 * @property string $id
 * @property ?string $branch_id
 * @property string $author_id
 * @property string $body
 * @property Carbon $created_at
 * @property User $author
 */
#[Fillable(['branch_id', 'author_id', 'body'])]
class ShiftNote extends Model
{
    use BelongsToTenant, HasUlids;

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
