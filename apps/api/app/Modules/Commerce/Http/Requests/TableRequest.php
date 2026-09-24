<?php

namespace App\Modules\Commerce\Http\Requests;

use App\Modules\Commerce\Models\RestaurantTable;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TableRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $table = $this->route('table');
        $table = $table instanceof RestaurantTable ? $table : null;

        return [
            'branch_id' => [$table ? 'prohibited' : 'required', 'string', TenantExists::in('branches')],
            'label' => [
                'required', 'string', 'max:40',
                Rule::unique('restaurant_tables', 'label')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->where('branch_id', $table !== null ? $table->branch_id : $this->input('branch_id'))
                    ->ignore($table?->getKey()),
            ],
            'capacity' => ['nullable', 'integer', 'between:1,50'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }
}
