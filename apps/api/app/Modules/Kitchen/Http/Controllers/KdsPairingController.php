<?php

namespace App\Modules\Kitchen\Http\Controllers;

use App\Modules\Kitchen\Actions\KitchenDevices;
use App\Support\Localization\PersianNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A tablet exchanges its one-time pairing code for its own token (rate-limited per IP). */
final class KdsPairingController
{
    public function pair(Request $request, KitchenDevices $devices): JsonResponse
    {
        $code = PersianNumber::toLatin((string) $request->validate(['code' => ['required', 'string', 'max:12']])['code']);
        ['device' => $device, 'token' => $token] = $devices->pair((string) preg_replace('/\D/', '', $code));

        return response()->json([
            'token' => $token,
            'device' => ['id' => $device->id, 'name' => $device->name, 'branch_id' => $device->branch_id, 'station_id' => $device->station_id],
        ]);
    }
}
