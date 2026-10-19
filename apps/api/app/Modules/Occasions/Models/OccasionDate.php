<?php

namespace App\Modules\Occasions\Models;

use App\Modules\Occasions\Enums\OccasionCalendar;
use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One occurrence of an occasion, as calendar days (no time). Hijri dates are approximate until a
 * platform admin confirms them (moon sighting can move them by a day).
 *
 * @property string $id
 * @property string $occasion_id
 * @property int $year
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $confirmed
 * @property Occasion $occasion
 */
#[Fillable(['occasion_id', 'year', 'starts_on', 'ends_on', 'confirmed'])]
class OccasionDate extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected function casts(): array
    {
        return ['year' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'confirmed' => 'boolean'];
    }

    /** @return BelongsTo<Occasion, $this> */
    public function occasion(): BelongsTo
    {
        return $this->belongsTo(Occasion::class);
    }

    public function isApproximate(): bool
    {
        return ! $this->confirmed && $this->occasion->calendar === OccasionCalendar::Hijri;
    }
}
