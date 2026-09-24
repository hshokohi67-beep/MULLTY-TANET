<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Enums\TableRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TableServiceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(TableRequestType::class)]];
    }
}
