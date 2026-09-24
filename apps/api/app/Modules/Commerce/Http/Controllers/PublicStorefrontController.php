<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Modules\Commerce\Contracts\OnlinePaymentGate;
use App\Modules\Commerce\Http\Requests\DeliveryCheckRequest;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Support\DeliveryQuoter;
use App\Modules\Commerce\Support\PreorderSchedule;
use App\Modules\Core\Http\Resources\BrandingResource;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantBranding;
use App\Modules\Core\Support\OpeningHoursEvaluator;
use App\Modules\Core\Support\TenantSettings;
use App\Support\Localization\Weekday;
use App\Support\Tenancy\TenantContext;
use App\Support\Validation\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the storefront shell needs in one call: the public tenant profile, its active
 * branches (hours, open now, delivery offered) and which features the customer can use.
 * Explicit allow-list: never add internal fields here.
 */
final class PublicStorefrontController
{
    public function show(TenantContext $context, OnlinePaymentGate $payments): JsonResponse
    {
        $tenant = $context->require();
        $branding = TenantBranding::query()->first();
        $withDelivery = DeliveryZone::query()->where('is_active', true)->distinct()->pluck('branch_id')->flip();
        $now = now();

        $branches = Branch::query()->where('is_active', true)->with('openingHours')->orderBy('sort')->orderBy('created_at')->get()
            ->map(function (Branch $b) use ($tenant, $withDelivery, $now): array {
                $intervals = $b->openingHours->map(fn (BranchOpeningHour $h) => ['weekday' => $h->weekday, 'opens_at' => $h->opens_at, 'closes_at' => $h->closes_at]);
                $hours = new OpeningHoursEvaluator($intervals, $tenant->timezone);
                // No schedule configured = always open (same rule as the order pricer).
                $open = $intervals->isEmpty() || $hours->isOpenAt($now);

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'phone' => $b->getAttribute('phone'),
                    'city' => $b->getAttribute('city'),
                    'address' => $b->getAttribute('address'),
                    'latitude' => $b->getAttribute('latitude'),
                    'longitude' => $b->getAttribute('longitude'),
                    'is_open' => $open,
                    'next_opening_at' => $open ? null : $hours->nextOpeningAfter($now)?->utc()->toIso8601String(),
                    'opening_hours' => $b->openingHours->map(fn (BranchOpeningHour $h) => [
                        'weekday' => $h->weekday,
                        'weekday_label' => Weekday::from($h->weekday)->label(),
                        'opens_at' => substr($h->opens_at, 0, 5),
                        'closes_at' => substr($h->closes_at, 0, 5),
                    ])->values()->all(),
                    'delivery' => $withDelivery->has($b->id) && $b->getAttribute('latitude') !== null,
                ];
            })->values();

        return response()->json(['data' => [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone,
            'branding' => $branding ? (new BrandingResource($branding))->resolve() : null,
            'contact' => [
                'phone' => TenantSettings::get('contact.phone'),
                'instagram' => TenantSettings::get('contact.instagram'),
            ],
            'branches' => $branches->all(),
            'features' => [
                'online_payment' => $payments->onlineAvailable(),
                'club' => (bool) TenantSettings::get('loyalty.enabled'),
                'wallet_payments' => (bool) TenantSettings::get('wallet.payments_enabled'),
                'preorder_when_closed' => (bool) TenantSettings::get('orders.allow_preorder_when_closed'),
            ],
        ]])->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=120');
    }

    /** Pre-order days and slots of a branch (opening hours, lead time, capacity), plus whether "now" is possible. */
    public function preorderSlots(Request $request, TenantContext $context): JsonResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', 'string', TenantExists::in('branches')]])['branch_id'];
        $branch = Branch::query()->where('is_active', true)->findOrFail($branchId);
        $schedule = PreorderSchedule::for($branch, $context->require()->timezone);

        return response()->json(['data' => [
            'open_now' => $schedule->isOpenNow(),
            'lead_minutes' => $schedule->leadMinutes,
            'slot_minutes' => $schedule->slotMinutes,
            'days' => $schedule->days(),
        ]])->header('Cache-Control', 'no-store');
    }

    /**
     * Zone check for a map pin before the address is saved. The minimum order is reported, not
     * enforced, here: the cart does not exist yet or may still grow.
     */
    public function deliveryCheck(DeliveryCheckRequest $request, DeliveryQuoter $quoter): JsonResponse
    {
        $v = $request->validated();
        $branch = Branch::query()->where('is_active', true)->findOrFail($v['branch_id']);
        $quote = $quoter->quote($branch, (float) $v['latitude'], (float) $v['longitude'], PHP_INT_MAX);

        return response()->json(['data' => [
            'zone_name' => $quote->zone->name,
            'distance_m' => $quote->distanceMeters,
            'fee' => $quote->zone->delivery_fee,
            'free_delivery_min' => $quote->zone->free_delivery_min,
            'min_order' => $quote->zone->min_order,
            'eta_minutes' => $quote->etaMinutes,
        ]]);
    }
}
