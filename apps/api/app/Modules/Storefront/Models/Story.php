<?php

namespace App\Modules\Storefront\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A storefront story: a photo with an optional caption and one call-to-action link, live between
 * starts_at and ends_at (24 hours by default).
 *
 * @property string $id
 * @property ?string $branch_id
 * @property string $image_path
 * @property string $thumb_path
 * @property int $width
 * @property int $height
 * @property ?string $caption
 * @property string $link_type none|product|category|url
 * @property ?string $link_target
 * @property ?string $cta_label
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $sort
 * @property bool $is_active
 * @property int $views
 * @property int $clicks
 */
#[Fillable(['branch_id', 'image_path', 'thumb_path', 'width', 'height', 'caption', 'link_type', 'link_target', 'cta_label', 'starts_at', 'ends_at', 'sort', 'is_active'])]
class Story extends Model
{
    use BelongsToTenant, HasUlids;

    public const LINK_TYPES = ['none', 'product', 'category', 'url'];

    public const DEFAULT_HOURS = 24;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'sort' => 'integer',
            'views' => 'integer',
            'clicks' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at->isPast() => 'expired',
            default => 'live',
        };
    }
}
