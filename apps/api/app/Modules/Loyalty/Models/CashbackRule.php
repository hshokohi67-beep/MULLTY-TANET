<?php

namespace App\Modules\Loyalty\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * "Spend at least min_spend (in this category, or on the whole order) → get cashback in the wallet".
 * Every matching rule adds up.
 *
 * @property string $id
 * @property string $name
 * @property ?string $category_id
 * @property int $min_spend rial
 * @property string $kind fixed | percent
 * @property int $value rial, or basis points for percent
 * @property ?int $max_reward
 * @property bool $is_active
 */
#[Fillable(['name', 'category_id', 'min_spend', 'kind', 'value', 'max_reward', 'is_active'])]
class CashbackRule extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['min_spend' => 'integer', 'value' => 'integer', 'max_reward' => 'integer', 'is_active' => 'boolean'];
    }

    /** Reward for the given eligible spend (0 when below the threshold). */
    public function rewardFor(int $spend): int
    {
        if ($spend <= 0 || $spend < $this->min_spend) {
            return 0;
        }

        $reward = $this->kind === 'percent' ? intdiv($spend * $this->value, 10_000) : $this->value;

        return $this->max_reward !== null ? min($reward, $this->max_reward) : $reward;
    }
}
