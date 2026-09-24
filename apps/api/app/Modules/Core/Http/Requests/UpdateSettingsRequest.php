<?php

namespace App\Modules\Core\Http\Requests;

use App\Modules\Core\Support\TenantSettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = ['settings' => ['required', 'array', 'min:1']];

        foreach (TenantSettingsRegistry::definitions() as $key => $definition) {
            // Keys contain dots, so address them literally inside the settings array.
            $rules['settings.'.str_replace('.', '\.', $key)] = $definition['rules'];
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('settings', [])) as $key) {
                    if (! TenantSettingsRegistry::has((string) $key)) {
                        $validator->errors()->add('settings', __('validation.unknown_setting', ['key' => $key]));
                    }
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return (array) $this->input('settings');
    }
}
