<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class QuoteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'address_id' => ['nullable', 'string', 'max:26'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
