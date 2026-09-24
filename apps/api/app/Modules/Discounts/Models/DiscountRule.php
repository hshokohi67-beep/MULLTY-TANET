<?php

namespace App\Modules\Discounts\Models;

use App\Modules\Discounts\Enums\DiscountRuleType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property DiscountRuleType $rule_type
 * @property string $target
 */
#[Fillable(['discount_id', 'rule_type', 'target'])]
class DiscountRule extends Model
{
    use BelongsToTenant, HasUlids;

    protected function casts(): array
    {
        return ['rule_type' => DiscountRuleType::class];
    }
}
