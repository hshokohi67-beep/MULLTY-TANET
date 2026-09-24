<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Http\Requests\DeliveryCheckRequest;
use App\Modules\Commerce\Http\Requests\DeliveryZoneRequest;
use App\Modules\Commerce\Http\Resources\DeliveryZoneResource;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Support\DeliveryQuoter;
use App\Modules\Core\Models\Branch;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DeliveryZoneController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $branchId = $request->validate(['branch_id' => ['nullable', 'string', 'max:26']])['branch_id'] ?? null;

        return DeliveryZoneResource::collection(
            DeliveryZone::query()->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('branch_id')->orderBy('radius_m')->get(),
        );
    }

    public function store(DeliveryZoneRequest $request, AuditLogger $audit): JsonResponse
    {
        $zone = DeliveryZone::query()->create([...$request->validated(), 'type' => 'radius']);
        $audit->record('delivery_zone.created', $zone, $request->validated());

        return (new DeliveryZoneResource($zone))->response()->setStatusCode(201);
    }

    public function update(DeliveryZoneRequest $request, DeliveryZone $zone, AuditLogger $audit): DeliveryZoneResource
    {
        $zone->fill($request->validated());
        $changes = $zone->getDirty();
        $zone->save();
        $audit->record('delivery_zone.updated', $zone, $changes);

        return new DeliveryZoneResource($zone);
    }

    public function destroy(DeliveryZone $zone, AuditLogger $audit): Response
    {
        $zone->delete();
        $audit->record('delivery_zone.deleted', $zone, ['name' => $zone->name]);

        return response()->noContent();
    }

    /** "Would we deliver here?": lets staff check a point on the map before promising a customer. */
    public function check(DeliveryCheckRequest $request, DeliveryQuoter $quoter): JsonResponse
    {
        $v = $request->validated();
        $branch = Branch::query()->findOrFail($v['branch_id']);
        $quote = $quoter->quote($branch, (float) $v['latitude'], (float) $v['longitude'], (int) ($v['subtotal'] ?? PHP_INT_MAX));

        return response()->json(['data' => $quote->toArray()]);
    }
}
