<?php

namespace App\Modules\Discounts\Actions;

use App\Modules\Discounts\Enums\DiscountRuleType;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Discounts\Models\DiscountRule;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SaveDiscount
{
    private const RULE_KEYS = [
        'product_ids' => DiscountRuleType::Product,
        'category_ids' => DiscountRuleType::Category,
        'branch_ids' => DiscountRuleType::Branch,
        'order_types' => DiscountRuleType::OrderType,
        'customer_ids' => DiscountRuleType::Customer,
        'tier_ids' => DiscountRuleType::Tier,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function handle(array $data, ?Discount $discount = null): Discount
    {
        return DB::transaction(function () use ($data, $discount): Discount {
            $discount ??= new Discount;
            $isNew = ! $discount->exists;
            $rules = $data['rules'] ?? null;
            unset($data['rules']);

            $discount->fill([
                ...$data,
                'min_order' => $data['min_order'] ?? 0,
                'priority' => $data['priority'] ?? 0,
                'schedule' => array_filter($data['schedule'] ?? [], fn ($v) => $v !== null && $v !== []) ?: null,
            ]);
            $changes = $discount->getDirty();
            $discount->save();

            if (is_array($rules)) {
                DiscountRule::query()->where('discount_id', $discount->getKey())->delete();

                foreach (self::RULE_KEYS as $key => $type) {
                    foreach (array_unique($rules[$key] ?? []) as $target) {
                        DiscountRule::query()->create(['discount_id' => $discount->getKey(), 'rule_type' => $type, 'target' => (string) $target]);
                    }
                }
            }

            $this->audit->record($isNew ? 'discount.created' : 'discount.updated', $discount, $changes);

            return $discount->load('rules');
        });
    }
}
