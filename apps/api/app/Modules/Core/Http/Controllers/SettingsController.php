<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\UpdateTenantSettings;
use App\Modules\Core\Http\Requests\UpdateSettingsRequest;
use App\Modules\Core\Models\TenantSetting;
use App\Modules\Core\Support\TenantSettingsRegistry;
use Illuminate\Http\JsonResponse;

final class SettingsController
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present(), 'meta' => $this->meta()]);
    }

    public function update(UpdateSettingsRequest $request, UpdateTenantSettings $update): JsonResponse
    {
        $update->handle($request->settings());

        return response()->json(['data' => $this->present(), 'meta' => $this->meta(), 'message' => __('messages.saved')]);
    }

    /**
     * Platform facts the settings screen explains (e.g. whether online payments run against a sandbox).
     *
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        $driver = (string) config('payments.driver');

        return ['payments' => [
            'driver' => $driver,
            'test_mode' => $driver === 'fake' || (bool) config('payments.gateways.zarinpal.sandbox'),
        ]];
    }

    /**
     * Every registered setting with its current value. Secrets are never returned:
     * only whether they are set, and a masked hint.
     *
     * @return list<array<string, mixed>>
     */
    private function present(): array
    {
        $stored = TenantSetting::query()->get()->keyBy('key');
        $items = [];

        foreach (TenantSettingsRegistry::definitions() as $key => $definition) {
            $setting = $stored->get($key);
            $plain = $setting?->plainValue();

            $items[] = [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'secret' => $definition['secret'],
                'value' => $definition['secret'] ? null : TenantSettingsRegistry::cast($key, $plain),
                'is_set' => $plain !== null && $plain !== '',
                'masked' => $definition['secret'] ? TenantSettingsRegistry::mask($plain) : null,
            ];
        }

        return $items;
    }
}
