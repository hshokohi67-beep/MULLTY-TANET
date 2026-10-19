<?php

namespace App\Modules\Occasions\Models;

use App\Modules\Occasions\Enums\OccasionCalendar;
use App\Modules\Occasions\Enums\OccasionKind;
use App\Modules\Occasions\Enums\OccasionTone;
use App\Support\Database\StoresDatesInUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An occasion in the platform calendar (not tenant-owned).
 *
 * @property string $id
 * @property string $key
 * @property string $title
 * @property ?string $description
 * @property OccasionKind $kind
 * @property OccasionTone $tone
 * @property OccasionCalendar $calendar
 * @property ?int $month
 * @property ?int $day
 * @property ?string $rule
 * @property int $duration_days
 * @property int $lead_days
 * @property bool $is_holiday
 * @property ?list<string> $ideas
 * @property ?string $campaign_text
 * @property ?string $theme
 * @property bool $is_active
 * @property bool $is_one_off
 * @property Collection<int, OccasionDate> $dates
 */
#[Fillable([
    'key', 'title', 'description', 'kind', 'tone', 'calendar', 'month', 'day', 'rule', 'duration_days', 'lead_days',
    'is_holiday', 'ideas', 'campaign_text', 'theme', 'is_active', 'is_one_off',
])]
class Occasion extends Model
{
    use HasUlids, StoresDatesInUtc;

    protected function casts(): array
    {
        return [
            'kind' => OccasionKind::class,
            'tone' => OccasionTone::class,
            'calendar' => OccasionCalendar::class,
            'month' => 'integer',
            'day' => 'integer',
            'duration_days' => 'integer',
            'lead_days' => 'integer',
            'is_holiday' => 'boolean',
            'ideas' => 'array',
            'is_active' => 'boolean',
            'is_one_off' => 'boolean',
        ];
    }

    /** @return HasMany<OccasionDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(OccasionDate::class);
    }
}
