<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $purchase_order_id
 * @property int $amount
 * @property string $method cash|card|transfer
 * @property ?string $note
 * @property Carbon $paid_at
 * @property ?string $recorded_by
 */
#[Fillable(['purchase_order_id', 'amount', 'method', 'note', 'paid_at', 'recorded_by'])]
class SupplierPayment extends Model
{
    use BelongsToTenant, HasUlids;

    public const METHODS = ['cash' => 'نقد', 'card' => 'کارت به کارت', 'transfer' => 'حواله‌ی بانکی'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }
}
