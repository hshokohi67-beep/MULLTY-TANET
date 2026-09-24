<?php

namespace App\Modules\Kitchen\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Kitchen\Exceptions\KitchenException;
use App\Modules\Kitchen\Models\KitchenDevice;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Kitchen\Models\KitchenStationProduct;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class ManageStations
{
    /**
     * Creates or updates a station. The first station of a branch becomes its default, and there is
     * always exactly one default per branch.
     *
     * @param  array{branch_id?: string, name: string, is_default?: bool, late_after_minutes?: int, is_active?: bool, sort?: int}  $data
     */
    public function save(?KitchenStation $station, array $data): KitchenStation
    {
        return DB::transaction(function () use ($station, $data): KitchenStation {
            $station ??= new KitchenStation(['branch_id' => $data['branch_id'] ?? null]);
            $station->fill(collect($data)->except('branch_id')->all());

            $hasDefault = KitchenStation::query()->where('branch_id', $station->branch_id)->where('is_default', true)->whereKeyNot($station->id)->exists();
            if (! $hasDefault) {
                $station->is_default = true;
            }

            $station->save();

            if ($station->is_default) {
                KitchenStation::query()->where('branch_id', $station->branch_id)->whereKeyNot($station->id)->update(['is_default' => false]);
            }

            app(AuditLogger::class)->record($station->wasRecentlyCreated ? 'kds.station_created' : 'kds.station_updated', $station, $station->only(['name', 'is_default', 'late_after_minutes', 'is_active']));

            return $station;
        });
    }

    public function delete(KitchenStation $station): void
    {
        // Past items keep their station for history, so a used station can only be deactivated.
        $busy = KitchenItem::query()->where('station_id', $station->id)->exists()
            || KitchenDevice::query()->where('station_id', $station->id)->whereNull('revoked_at')->exists();

        if ($busy) {
            throw KitchenException::stationInUse();
        }

        DB::transaction(function () use ($station): void {
            $station->delete();

            // Keep exactly one default.
            if ($station->is_default) {
                KitchenStation::query()->where('branch_id', $station->branch_id)->orderBy('sort')->first()?->forceFill(['is_default' => true])->save();
            }
        });

        app(AuditLogger::class)->record('kds.station_deleted', $station, ['name' => $station->name]);
    }

    /**
     * Sets which products this station prepares in its branch. A product moves here from any
     * other station of the same branch.
     *
     * @param  list<string>  $productIds
     */
    public function syncProducts(KitchenStation $station, array $productIds): void
    {
        $productIds = Product::query()->whereKey($productIds)->pluck('id')->all();

        DB::transaction(function () use ($station, $productIds): void {
            KitchenStationProduct::query()->where('station_id', $station->id)->whereNotIn('product_id', $productIds)->delete();
            KitchenStationProduct::query()->where('branch_id', $station->branch_id)->whereIn('product_id', $productIds)->where('station_id', '!=', $station->id)->delete();

            $existing = KitchenStationProduct::query()->where('station_id', $station->id)->pluck('product_id')->all();
            foreach (array_diff($productIds, $existing) as $productId) {
                KitchenStationProduct::query()->create(['branch_id' => $station->branch_id, 'station_id' => $station->id, 'product_id' => $productId]);
            }
        });

        app(AuditLogger::class)->record('kds.station_routing_updated', $station, ['products' => count($productIds)]);
    }
}
