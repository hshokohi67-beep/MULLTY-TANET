<?php

namespace App\Modules\Operations\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $category_id
 * @property int $amount
 * @property Carbon $spent_on
 * @property string $method
 * @property ?string $payee
 * @property ?string $note
 * @property ?string $recorded_by
 * @property ExpenseCategory $category
 */
#[Fillable(['branch_id', 'category_id', 'amount', 'spent_on', 'method', 'payee', 'note', 'recorded_by'])]
class Expense extends Model
{
    use BelongsToTenant, HasUlids;

    public const METHODS = ['cash' => 'نقد', 'card' => 'کارت', 'transfer' => 'حواله‌ی بانکی'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }
}
