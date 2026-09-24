<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Core\Models\Branch;
use App\Support\Money\Money;
use App\Support\Money\MoneyFormatter;

/**
 * Delivery eligibility pipeline (master prompt §10):
 * address → branch → distance → zone → fee → minimum order.
 *
 * V1 zones are radius-based: the smallest active zone containing the address wins, so
 * concentric zones give tiered fees ("up to 2 km free, up to 5 km 30,000 toman").
 * Polygon zones plug in here later without changing callers.
 */
final class DeliveryQuoter
{
    /**
     * @param  int  $subtotal  rial, the order's item value (used for minimum order and free delivery)
     */
    public function quote(Branch $branch, ?float $latitude, ?float $longitude, int $subtotal): DeliveryQuote
    {
        if ($latitude === null || $longitude === null) {
            throw CommerceException::addressNeedsLocation();
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            throw CommerceException::deliveryNotConfigured();
        }

        $zones = DeliveryZone::query()
            ->where('branch_id', $branch->getKey())
            ->where('is_active', true)
            ->where('type', 'radius')
            ->whereNotNull('radius_m')
            ->orderBy('radius_m')
            ->get();

        if ($zones->isEmpty()) {
            throw CommerceException::deliveryNotConfigured();
        }

        $distance = Geo::distanceMeters($branch->latitude, $branch->longitude, $latitude, $longitude);
        $zone = $zones->first(fn (DeliveryZone $z) => $distance <= (int) $z->radius_m);

        if ($zone === null) {
            throw CommerceException::outOfDeliveryArea();
        }

        if ($subtotal < $zone->min_order) {
            throw CommerceException::belowMinimumOrder(MoneyFormatter::format(Money::rials($zone->min_order)));
        }

        $free = $zone->free_delivery_min !== null && $subtotal >= $zone->free_delivery_min;

        return new DeliveryQuote($zone, $distance, $free ? 0 : $zone->delivery_fee, $free, $zone->eta_minutes);
    }
}
