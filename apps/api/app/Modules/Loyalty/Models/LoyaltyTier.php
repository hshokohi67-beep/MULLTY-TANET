<?php

namespace App\Modules\Loyalty\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A spend-based tier. A customer is in the highest tier whose min_spend they reached;
 * tiers only go up automatically.
 *
 * @property string $id
 * @property string $name
 * @property int $min_spend rial, lifetime
 * @property string $color
 * @property int $points_multiplier basis points (10000 = x1)
 * @property ?string $perks
 * @property int $sort
 */
#[Fillable(['name', 'min_spend', 'color', 'points_multiplier', 'perks', 'sort'])]
class LoyaltyTier extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['min_spend' => 'integer', 'points_multiplier' => 'integer', 'sort' => 'integer'];
    }
}
