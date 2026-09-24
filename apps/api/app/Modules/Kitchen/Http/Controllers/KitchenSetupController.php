<?php

namespace App\Modules\Kitchen\Http\Controllers;

use App\Modules\Kitchen\Actions\KitchenDevices;
use App\Modules\Kitchen\Actions\ManageStations;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Kitchen\Models\KitchenStationProduct;
use App\Support\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Stations, product routing and paired devices (kds.manage). */
final class KitchenSetupController
{
    public function index(): JsonResponse
    {
        $routes = KitchenStationProduct::query()->get(['station_id', 'product_id'])->groupBy('station_id');

        return response()->json(['data' => [
            'stations' => KitchenStation::query()->orderBy('branch_id')->orderByDesc('is_default')->orderBy('sort')->get()
                ->map(fn (KitchenStation $s) => [
                    ...$s->only(['id', 'branch_id', 'name', 'is_default', 'late_after_minutes', 'is_active', 'sort']),
                    'product_ids' => $routes->get($s->id)?->pluck('product_id')->values() ?? [],
                ])->values(),
            'devices' => KitchenDevice::query()->latest()->get()->map(fn (KitchenDevice $d) => $this->device($d))->values(),
        ]]);
    }

    public function storeStation(Request $request, ManageStations $stations): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            ...$this->stationRules(),
        ]);

        return response()->json(['data' => $stations->save(null, $data)], 201);
    }

    public function updateStation(Request $request, string $station, ManageStations $stations): JsonResponse
    {
        $model = KitchenStation::query()->findOrFail($station);

        return response()->json(['data' => $stations->save($model, $request->validate($this->stationRules()))]);
    }

    public function destroyStation(string $station, ManageStations $stations): Response
    {
        $stations->delete(KitchenStation::query()->findOrFail($station));

        return response()->noContent();
    }

    public function syncProducts(Request $request, string $station, ManageStations $stations): Response
    {
        $model = KitchenStation::query()->findOrFail($station);
        $ids = $request->validate([
            'product_ids' => ['present', 'array', 'max:1000'],
            'product_ids.*' => ['string', TenantExists::in('products', withoutTrashed: true)],
        ])['product_ids'];

        $stations->syncProducts($model, array_values(array_map('strval', $ids)));

        return response()->noContent();
    }

    public function storeDevice(Request $request, KitchenDevices $devices): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'branch_id' => ['required', 'string', TenantExists::in('branches')],
            'station_id' => ['nullable', 'string', TenantExists::in('kitchen_stations')],
        ]);

        if (! empty($data['station_id']) && KitchenStation::query()->whereKey($data['station_id'])->value('branch_id') !== $data['branch_id']) {
            throw KitchenException::stationBranchMismatch();
        }

        ['device' => $device, 'code' => $code] = $devices->create($data);

        return response()->json(['data' => $this->device($device), 'pairing_code' => $code, 'expires_in' => KitchenDevices::CODE_TTL_MINUTES * 60], 201);
    }

    public function repairDevice(string $device, KitchenDevices $devices): JsonResponse
    {
        $model = KitchenDevice::query()->findOrFail($device);
        $code = $devices->repair($model);

        return response()->json(['data' => $this->device($model->refresh()), 'pairing_code' => $code, 'expires_in' => KitchenDevices::CODE_TTL_MINUTES * 60]);
    }

    public function revokeDevice(string $device, KitchenDevices $devices): JsonResponse
    {
        $model = KitchenDevice::query()->findOrFail($device);
        $devices->revoke($model);

        return response()->json(['data' => $this->device($model->refresh())]);
    }

    /** @return array<string, mixed> */
    private function stationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'is_default' => ['sometimes', 'boolean'],
            'late_after_minutes' => ['sometimes', 'integer', 'between:1,120'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    /** @return array<string, mixed> */
    private function device(KitchenDevice $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'branch_id' => $d->branch_id,
            'station_id' => $d->station_id,
            'status' => match (true) {
                $d->revoked_at !== null => 'revoked',
                $d->paired_at !== null => 'paired',
                default => 'waiting',
            },
            'pairing_expires_at' => $d->pairing_expires_at?->toIso8601String(),
            'paired_at' => $d->paired_at?->toIso8601String(),
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
        ];
    }
}
