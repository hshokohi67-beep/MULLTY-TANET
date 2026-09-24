<?php

namespace App\Modules\Identity\Http\Requests;

use App\Support\Localization\PhoneNormalizer;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class AddTeamMemberRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['phone']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new IranianMobile],
            'password' => ['nullable', 'string', Password::min(8)],
            'role_ids' => ['required', 'array', 'min:1', 'max:10'],
            'role_ids.*' => ['required', 'string', Rule::exists('roles', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ];
    }

    public function phoneE164(): string
    {
        return PhoneNormalizer::normalize((string) $this->validated('phone'));
    }
}
