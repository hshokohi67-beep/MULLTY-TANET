<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

final class BranchPricesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'prices' => ['required', 'array', 'min:1', 'max:10'],
            'prices.*.variant_id' => ['required', 'string', 'distinct'],
            // null = remove the override so the base price applies again
            'prices.*.amount' => ['present', 'nullable', 'integer', 'min:0', 'max:'.ProductRequest::MAX_PRICE],
        ];
    }

    /** @return array<string, ?int> */
    public function amounts(): array
    {
        $amounts = [];

        foreach ((array) $this->validated('prices') as $price) {
            $amounts[(string) $price['variant_id']] = $price['amount'] === null ? null : (int) $price['amount'];
        }

        return $amounts;
    }
}
