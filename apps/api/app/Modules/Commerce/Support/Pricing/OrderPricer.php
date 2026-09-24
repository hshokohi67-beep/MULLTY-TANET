<?php

namespace App\Modules\Commerce\Support\Pricing;

use App\Modules\Catalog\Enums\AvailabilityStatus;
use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceResolver;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Support\DeliveryQuoter;
use App\Modules\Commerce\Support\PreorderSchedule;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Support\OpeningHoursEvaluator;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Discounts\Contracts\CustomerTierLookup;
use App\Modules\Discounts\Exceptions\CouponException;
use App\Modules\Discounts\Support\DiscountContext;
use App\Modules\Discounts\Support\DiscountEngine;
use App\Support\Localization\JalaliDate;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The one pricing engine for cart previews and checkout (master prompt §3/§10/§16).
 * Every amount comes from the live menu, never from the client. Integer rial throughout.
 *
 * lenient (cart preview): problems are reported per line / as issues, totals exclude bad lines.
 * strict  (checkout):     the first problem is thrown.
 */
final class OrderPricer
{
    public function __construct(
        private readonly DiscountEngine $discounts,
        private readonly DeliveryQuoter $delivery,
    ) {}

    public function price(PricingRequest $request, bool $strict): PricedOrder
    {
        // Resolved per call: the context is request-scoped, this service may outlive a request.
        $timezone = app(TenantContext::class)->require()->timezone;
        $now = CarbonImmutable::now($timezone);
        $issues = [];

        $scheduledFor = $this->checkSchedule($request, $now, $timezone, $strict, $issues);

        $lines = $this->priceLines($request, $strict);
        $valid = array_filter($lines, fn (PricedLine $l) => $l->isValid());
        $subtotal = array_sum(array_map(fn (PricedLine $l) => $l->lineTotal, $valid));

        $discount = null;
        if ($valid !== []) {
            try {
                $discount = $this->discounts->apply(new DiscountContext(
                    branchId: $request->branch->getKey(),
                    orderType: $request->orderType->value,
                    customerId: $request->customerId,
                    lines: array_values(array_map(fn (PricedLine $l) => ['product_id' => $l->productId, 'category_ids' => $l->categoryIds, 'line_total' => $l->lineTotal], $valid)),
                    subtotal: $subtotal,
                    now: $scheduledFor ?? $now,
                    couponCode: $request->couponCode,
                    customerTierId: app(CustomerTierLookup::class)->tierIdFor($request->customerId),
                ));
            } catch (CouponException $e) {
                if ($strict) {
                    throw $e;
                }
                $issues[] = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            }
        }

        $quote = null;
        if ($request->orderType->needsAddress() && $valid !== []) {
            try {
                if ($request->address === null) {
                    throw CommerceException::addressRequired();
                }
                $quote = $this->delivery->quote($request->branch, $request->address->latitude, $request->address->longitude, $subtotal);
            } catch (CommerceException $e) {
                if ($strict) {
                    throw $e;
                }
                $issues[] = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            }
        }

        $total = $subtotal - ($discount->amount ?? 0) + ($quote->fee ?? 0);

        return new PricedOrder($lines, $subtotal, $discount, $quote, max(0, $total), $scheduledFor, $issues);
    }

    /**
     * @param  list<array{code: string, message: string}>  $issues
     */
    private function checkSchedule(PricingRequest $request, CarbonImmutable $now, string $timezone, bool $strict, array &$issues): ?CarbonImmutable
    {
        $scheduled = $request->scheduledFor?->setTimezone($timezone);
        $schedule = PreorderSchedule::for($request->branch, $timezone, $now);

        try {
            if ($scheduled !== null) {
                // Lead time, last day, opening hours and slot capacity: the same rules as the picker.
                $schedule->validate($scheduled, $strict);
            } elseif (! $schedule->isOpenNow()) {
                if (! TenantSettings::get('orders.allow_preorder_when_closed')) {
                    throw CommerceException::preorderDisabled();
                }

                $intervals = BranchOpeningHour::query()->where('branch_id', $request->branch->getKey())->get()
                    ->map(fn (BranchOpeningHour $h) => ['weekday' => $h->weekday, 'opens_at' => $h->opens_at, 'closes_at' => $h->closes_at]);
                $next = (new OpeningHoursEvaluator($intervals, $timezone))->nextOpeningAfter($now);
                throw CommerceException::branchClosed($next ? JalaliDate::format($next, 'EEEE HH:mm', $timezone) : null);
            }
        } catch (CommerceException $e) {
            if ($strict) {
                throw $e;
            }
            $issues[] = ['code' => $e->errorCode, 'message' => $e->getMessage()];
        }

        return $scheduled;
    }

    /** @return list<PricedLine> */
    private function priceLines(PricingRequest $request, bool $strict): array
    {
        $branchId = $request->branch->getKey();
        $productIds = array_values(array_unique(array_column($request->lines, 'product_id')));

        /** @var Collection<string, Product> $products */
        $products = Product::query()
            ->whereKey($productIds)
            ->with([
                'variants.prices',
                'categories:id',
                'modifierGroups.modifiers',
                'availability' => fn ($q) => $q->where('branch_id', $branchId),
            ])
            ->get()
            ->keyBy('id');

        $priced = [];

        foreach ($request->lines as $line) {
            $product = $products->get($line['product_id']);
            $result = $this->priceLine($line, $product, $branchId);

            if ($strict && $result->problem !== null) {
                throw $result->problem;
            }

            $priced[] = $result;
        }

        return $priced;
    }

    /**
     * @param  array{product_id: string, variant_id: string, quantity: int, modifier_ids?: ?list<string>, note?: ?string, ref?: ?string}  $line
     */
    private function priceLine(array $line, ?Product $product, string $branchId): PricedLine
    {
        $ref = $line['ref'] ?? null;
        $quantity = max(1, (int) $line['quantity']);
        $note = $line['note'] ?? null;

        $fail = fn (string $name, CommerceException $problem) => new PricedLine($ref, $line['product_id'], $line['variant_id'], $name, null, 0, [], 0, $quantity, 0, $note, [], $problem);

        if ($product === null || ! $product->is_active) {
            return $fail($product->name ?? 'آیتم حذف‌شده', CommerceException::productUnavailable($product->name ?? 'این آیتم'));
        }

        $status = $product->availability->first()?->effectiveStatus() ?? AvailabilityStatus::Available;
        /** @var ProductVariant|null $variant */
        $variant = $product->variants->firstWhere('id', $line['variant_id']);
        $unitPrice = $variant ? PriceResolver::amountFor($variant, $branchId) : null;

        if ($status !== AvailabilityStatus::Available || $variant === null || ! $variant->is_active || $unitPrice === null) {
            return $fail($product->name, CommerceException::productUnavailable($product->name));
        }

        $modifierIds = array_values(array_unique($line['modifier_ids'] ?? []));
        $chosen = [];

        /** @var ModifierGroup $group */
        foreach ($product->modifierGroups as $group) {
            $picked = $group->modifiers->filter(fn (Modifier $m) => $m->is_active && in_array($m->id, $modifierIds, true));
            $count = $picked->count();

            if ($count < $group->min_select || ($group->max_select > 0 && $count > $group->max_select)) {
                return $fail($product->name, CommerceException::modifierSelection($product->name, $group->name, $group->min_select, $group->max_select));
            }

            foreach ($picked as $modifier) {
                $chosen[$modifier->id] = ['modifier_id' => $modifier->id, 'group_name' => $group->name, 'name' => $modifier->name, 'price_delta' => $modifier->price_delta];
            }
        }

        // Any id that isn't an active modifier of this product's groups is rejected outright.
        if (count($chosen) !== count($modifierIds)) {
            return $fail($product->name, CommerceException::modifierInvalid($product->name));
        }

        $modifiersTotal = array_sum(array_column($chosen, 'price_delta'));
        $lineTotal = max(0, ($unitPrice + $modifiersTotal) * $quantity);

        return new PricedLine(
            $ref, $product->id, $variant->id, $product->name, $variant->name, $unitPrice,
            array_values($chosen), $modifiersTotal, $quantity, $lineTotal, $note, $product->categories->modelKeys(),
        );
    }
}
