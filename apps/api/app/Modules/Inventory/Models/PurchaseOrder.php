<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $supplier_id
 * @property string $branch_id
 * @property int $number
 * @property string $status draft|ordered|received|cancelled
 * @property ?Carbon $expected_on
 * @property int $total
 * @property int $paid_total
 * @property ?string $note
 * @property ?Carbon $ordered_at
 * @property ?Carbon $received_at
 * @property ?string $created_by
 * @property Carbon $created_at
 * @property Supplier $supplier
 * @property Branch $branch
 * @property Collection<int, PurchaseOrderItem> $items
 * @property Collection<int, SupplierPayment> $payments
 */
#[Fillable(['supplier_id', 'branch_id', 'number', 'status', 'expected_on', 'total', 'paid_total', 'note', 'ordered_at', 'received_at', 'created_by'])]
class PurchaseOrder extends Model
{
    use BelongsToTenant, HasUlids;

    public const STATUS_LABELS = ['draft' => 'پیش‌نویس', 'ordered' => 'سفارش داده شد', 'received' => 'تحویل گرفته شد', 'cancelled' => 'لغو شد'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'expected_on' => 'date',
            'total' => 'integer',
            'paid_total' => 'integer',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Orders we actually owe for: not cancelled and at least partly delivered (an order that has
     * only been placed is not a debt yet).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwing($query)
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('purchase_order_items')->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id')->where('received_quantity', '>', 0));
    }

    public function hasReceipts(): bool
    {
        return $this->status === 'received' || $this->items()->where('received_quantity', '>', 0)->exists();
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->paid_total);
    }
}
