<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JoinTableRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['qr_token' => ['required', 'string', 'max:64']];
    }
}
