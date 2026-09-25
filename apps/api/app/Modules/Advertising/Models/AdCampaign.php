<?php

namespace App\Modules\Advertising\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A café's ad campaign. Lifecycle: draft → pending (review) → approved → paid; rejected goes back
 * to editing, cancelled ends it before payment, suspended pauses a paid one. A paid campaign is
 * scheduled / running / ended by its dates (see phase()).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $ref
 * @property string $name
 * @property string $placement
 * @property string $status
 * @property Carbon $start_date
 * @property int $days
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property list<string> $cities
 * @property string $headline
 * @property ?string $body
 * @property string $cta
 * @property ?string $image_path
 * @property ?string $image_small_path
 * @property int $daily_price
 * @property int $amount
 * @property ?string $invoice_id
 * @property ?string $review_note
 * @property ?string $payment_issue
 * @property ?Carbon $submitted_at
 * @property ?Carbon $reviewed_at
 * @property ?Carbon $paid_at
 * @property ?Carbon $suspended_at
 * @property ?string $created_by
 * @property Carbon $created_at
 */
#[Fillable(['name', 'placement', 'status', 'start_date', 'days', 'starts_at', 'ends_at', 'cities', 'headline', 'body', 'cta', 'image_path', 'image_small_path', 'daily_price', 'amount', 'invoice_id', 'review_note', 'payment_issue', 'submitted_at', 'reviewed_at', 'paid_at', 'suspended_at', 'created_by'])]
class AdCampaign extends Model
{
    use BelongsToTenant, HasUlids;

    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const PAID = 'paid';

    public const SUSPENDED = 'suspended';

    public const CANCELLED = 'cancelled';

    /** Statuses the café may still edit (editing an approved one sends it back to review). */
    public const EDITABLE = [self::DRAFT, self::PENDING, self::APPROVED, self::REJECTED];

    /** Call-to-action labels (the link itself is always the café's own page). */
    public const CTAS = ['menu' => 'دیدن منو', 'order' => 'سفارش آنلاین', 'offer' => 'دیدن تخفیف', 'visit' => 'آشنایی با کافه'];

    public const MAX_DAYS = 30;

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'cities' => 'array',
            'days' => 'integer', 'daily_price' => 'integer', 'amount' => 'integer',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'paid_at' => 'datetime', 'suspended_at' => 'datetime',
        ];
    }

    /** @return HasMany<AdDailyStat, $this> */
    public function stats(): HasMany
    {
        return $this->hasMany(AdDailyStat::class, 'campaign_id');
    }

    /** Where a paid campaign is in its dates: scheduled | running | ended (null before payment). */
    public function phase(?Carbon $now = null): ?string
    {
        if ($this->status !== self::PAID) {
            return null;
        }
        $now ??= now();

        return $now->lt($this->starts_at) ? 'scheduled' : ($now->lt($this->ends_at) ? 'running' : 'ended');
    }
}
