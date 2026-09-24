<?php

namespace App\Modules\Identity\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMemberRolesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1', 'max:10'],
            'role_ids.*' => ['required', 'string', Rule::exists('roles', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ];
    }
}
