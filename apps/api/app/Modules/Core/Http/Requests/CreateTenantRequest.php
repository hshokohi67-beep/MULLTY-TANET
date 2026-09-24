<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Data\CreateTenantData;
use App\Support\Localization\PhoneNormalizer;
use App\Support\Validation\IranianMobile;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateTenantRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['owner_phone']);
        $this->merge(['slug' => mb_strtolower((string) $this->input('slug'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(config('tenancy.reserved_slugs')), 'unique:tenants,slug'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['nullable', 'email:rfc', 'max:190', 'required_without:owner_phone'],
            'owner_phone' => ['nullable', 'string', new IranianMobile, 'required_without:owner_email'],
            'owner_password' => ['required', 'string', Password::min(8)],
            'branch_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function toData(): CreateTenantData
    {
        $v = $this->validated();

        return new CreateTenantData(
            name: $v['name'],
            slug: $v['slug'],
            ownerName: $v['owner_name'],
            ownerEmail: isset($v['owner_email']) ? mb_strtolower($v['owner_email']) : null,
            ownerPhoneE164: PhoneNormalizer::tryNormalize($v['owner_phone'] ?? null),
            ownerPassword: $v['owner_password'],
            firstBranchName: $v['branch_name'] ?? 'شعبه مرکزی',
            subdomainBase: config('tenancy.subdomain_base'),
        );
    }
}
