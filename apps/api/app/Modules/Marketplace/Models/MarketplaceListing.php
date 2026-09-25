<?php

namespace App\Modules\Marketplace\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The café's marketplace entry (opt-in) plus platform moderation.
 *
 * @property string $id
 * @property string $tenant_id
 * @property bool $is_listed
 * @property ?string $headline
 * @property ?string $about
 * @property list<string> $categories
 * @property list<string> $amenities
 * @property ?int $price_level
 * @property ?Carbon $listed_at
 * @property ?Carbon $hidden_at
 * @property ?string $hidden_reason
 * @property ?Carbon $featured_until
 */
#[Fillable(['is_listed', 'headline', 'about', 'categories', 'amenities', 'price_level', 'listed_at', 'hidden_at', 'hidden_reason', 'featured_until'])]
class MarketplaceListing extends Model
{
    use BelongsToTenant, HasUlids;

    protected $attributes = ['categories' => '[]', 'amenities' => '[]'];

    protected function casts(): array
    {
        return [
            'is_listed' => 'boolean', 'categories' => 'array', 'amenities' => 'array', 'price_level' => 'integer',
            'listed_at' => 'datetime', 'hidden_at' => 'datetime', 'featured_until' => 'datetime',
        ];
    }
}
