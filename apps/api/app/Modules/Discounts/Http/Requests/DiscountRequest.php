<?php

namespace App\Modules\Discounts\Http\Requests;

use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Discounts\Enums\DiscountKind;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DiscountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code'))) ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $discount = $this->route('discount');
        $percent = $this->input('kind') === DiscountKind::Percent->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('discounts', 'code')->where('tenant_id', app(TenantContext::class)->id())->ignore($discount?->getKey()),
            ],
            'kind' => ['required', Rule::enum(DiscountKind::class)],
            // percent: basis points (1–100%) · fixed: rial
            'value' => ['required', 'integer', $percent ? 'between:1,10000' : 'between:1,100000000000'],
            'applies_to' => ['required', Rule::in(['order', 'items'])],
            'min_order' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'schedule' => ['nullable', 'array:weekdays,from,to'],
            'schedule.weekdays' => ['nullable', 'array', 'max:7'],
            'schedule.weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'schedule.from' => ['nullable', 'date_format:H:i', 'required_with:schedule.to'],
            'schedule.to' => ['nullable', 'date_format:H:i', 'required_with:schedule.from'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['nullable', 'integer', 'between:-100,100'],
            'rules' => ['sometimes', 'array', 'max:200'],
            'rules.product_ids' => ['sometimes', 'array'],
            'rules.product_ids.*' => ['string', TenantExists::in('products', withoutTrashed: true)],
            'rules.category_ids' => ['sometimes', 'array'],
            'rules.category_ids.*' => ['string', TenantExists::in('categories')],
            'rules.branch_ids' => ['sometimes', 'array'],
            'rules.branch_ids.*' => ['string', TenantExists::in('branches')],
            'rules.order_types' => ['sometimes', 'array'],
            'rules.order_types.*' => [Rule::enum(OrderType::class)],
            'rules.customer_ids' => ['sometimes', 'array', 'max:100'],
            'rules.customer_ids.*' => ['string', TenantExists::in('customers')],
            'rules.tier_ids' => ['sometimes', 'array', 'max:20'],
            'rules.tier_ids.*' => ['string', TenantExists::in('loyalty_tiers')],
        ];
    }
}
