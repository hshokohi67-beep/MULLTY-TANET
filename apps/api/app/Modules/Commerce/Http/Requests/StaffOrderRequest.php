<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Models\CartItem;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StaffOrderRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['contact_phone']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'type' => ['required', Rule::in(array_map(fn (OrderType $t) => $t->value, OrderType::staffFacing()))],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.variant_id' => ['required', 'string', TenantExists::in('product_variants')],
            'lines.*.quantity' => ['required', 'integer', 'between:1,'.CartItem::MAX_QUANTITY],
            'lines.*.modifier_ids' => ['sometimes', 'array', 'max:30'],
            'lines.*.modifier_ids.*' => ['string', TenantExists::in('modifiers')],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', new IranianMobile],
            'note' => ['nullable', 'string', 'max:300'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ];
    }

    public function contactPhoneE164(): ?string
    {
        return PhoneNormalizer::tryNormalize($this->validated('contact_phone'));
    }
}
