<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Data\VariantData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class VariantsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ProductRequest::variantRules();
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $v) => ProductRequest::requireVariantNames($v, (array) $this->input('variants', []))];
    }

    /** @return list<VariantData> */
    public function variants(): array
    {
        return ProductRequest::toVariantData($this->validated('variants')) ?? [];
    }
}
