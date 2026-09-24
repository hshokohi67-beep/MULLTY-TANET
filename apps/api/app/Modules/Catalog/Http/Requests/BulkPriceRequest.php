<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Support\BulkPriceOperation;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BulkPriceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $operation = BulkPriceOperation::tryFrom((string) $this->input('operation'));

        $valueRule = match ($operation) {
            BulkPriceOperation::PercentDecrease => 'between:1,10000',       // basis points, up to 100%
            BulkPriceOperation::PercentIncrease => 'between:1,100000',      // up to 1000%
            BulkPriceOperation::Exact => 'between:0,'.ProductRequest::MAX_PRICE,
            default => 'between:1,'.ProductRequest::MAX_PRICE,
        };

        return [
            'target' => ['present', 'array'],
            'target.all' => ['sometimes', 'boolean'],
            'target.product_ids' => ['sometimes', 'array', 'max:500'],
            'target.product_ids.*' => ['string', TenantExists::in('products', withoutTrashed: true)],
            'target.category_ids' => ['sometimes', 'array', 'max:50'],
            'target.category_ids.*' => ['string', TenantExists::in('categories')],
            'branch_id' => ['nullable', 'string', TenantExists::in('branches')],
            'operation' => ['required', Rule::enum(BulkPriceOperation::class)],
            'value' => ['required', 'integer', $valueRule],
            'round_to' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'preview' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = (array) $this->input('target', []);

            if (empty($target['all']) && empty($target['product_ids']) && empty($target['category_ids'])) {
                $validator->errors()->add('target', __('validation.bulk_target_required'));
            }
        }];
    }

    /** @return array{product_ids?: list<string>, category_ids?: list<string>, all?: bool} */
    public function target(): array
    {
        /** @var array{product_ids?: list<string>, category_ids?: list<string>, all?: bool} */
        return $this->validated('target');
    }
}
