<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Data\BranchData;
use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\NormalizesPersianInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BranchRequest extends FormRequest
{
    use NormalizesPersianInput;

    protected function prepareForValidation(): void
    {
        $this->latinDigits(['phone', 'postal_code']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Branch|null $branch */
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('branches', 'slug')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore($branch?->getKey()),
            ],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{8,15}$/'],
            'province' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'digits:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): BranchData
    {
        $v = $this->validated();

        return new BranchData(
            name: $v['name'],
            slug: $v['slug'],
            phone: $v['phone'] ?? null,
            province: $v['province'] ?? null,
            city: $v['city'] ?? null,
            address: $v['address'] ?? null,
            postalCode: $v['postal_code'] ?? null,
            latitude: isset($v['latitude']) ? (float) $v['latitude'] : null,
            longitude: isset($v['longitude']) ? (float) $v['longitude'] : null,
            isActive: (bool) ($v['is_active'] ?? true),
        );
    }
}
