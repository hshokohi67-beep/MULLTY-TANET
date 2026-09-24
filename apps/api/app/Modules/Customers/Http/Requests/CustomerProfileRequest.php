<?php

namespace App\Modules\Customers\Http\Requests;

use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;

/** Used by the customer (own profile) and by staff (then staff_note is accepted too). */
final class CustomerProfileRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['birth_month', 'birth_day']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'birth_month' => ['sometimes', 'nullable', 'integer', 'between:1,12'],
            'birth_day' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'staff_note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{name?: ?string, birth_month?: ?int, birth_day?: ?int, marketing_opt_in?: bool, staff_note?: ?string}
     */
    public function profile(): array
    {
        $v = $this->validated();

        foreach (['birth_month', 'birth_day'] as $key) {
            if (array_key_exists($key, $v)) {
                $v[$key] = $v[$key] === null ? null : (int) $v[$key];
            }
        }

        /** @var array{name?: ?string, birth_month?: ?int, birth_day?: ?int, marketing_opt_in?: bool, staff_note?: ?string} $v */
        return $v;
    }
}
