<?php

namespace Database\Seeders;

use App\Modules\Analytics\Actions\RollupDay;
use App\Modules\Analytics\Models\MetricDirtyDay;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Core\Models\Branch;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\OrderItemCost;
use App\Modules\Inventory\Support\RecipeCalculator;
use App\Modules\Payments\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo history for the reports: ~90 days of completed orders across both branches with a weekly
 * rhythm (Thursday/Friday busiest), morning/lunch/evening peaks, a mild growth trend, a few
 * cancellations and mixed payment methods. Written directly (no stock/kitchen side effects),
 * skipping days that already have orders. Deterministic (seeded RNG). Dev/demo only.
 */
class AnalyticsDemoSeeder extends Seeder
{
    private const HOUR_WEIGHTS = [8 => 3, 9 => 6, 10 => 8, 11 => 6, 12 => 7, 13 => 8, 14 => 5, 15 => 4, 16 => 5, 17 => 7, 18 => 9, 19 => 10, 20 => 9, 21 => 7, 22 => 4];

    public function run(TenantContext $context): void
    {
        $tz = $context->require()->timezone;
        mt_srand(1405);

        $variants = ProductVariant::query()->with(['product:id,name', 'prices'])->where('is_active', true)->get()
            ->filter(fn (ProductVariant $v) => $v->basePrice() !== null)->values();
        if ($variants->isEmpty()) {
            return;
        }
        $customers = Customer::query()->pluck('id')->all();
        // Recipe cost per unit of each variant, so food cost and margins look real.
        $recipes = RecipeCalculator::load($variants->pluck('id')->all(), []);
        $unitCost = $variants->mapWithKeys(fn (ProductVariant $v) => [$v->id => RecipeCalculator::cost(RecipeCalculator::usage($recipes, $v->id, []), $recipes['ingredients'])])->all();
        $branches = Branch::query()->orderBy('created_at')->pluck('id')->all();
        $today = CarbonImmutable::now($tz)->startOfDay();
        $hours = [];
        foreach (self::HOUR_WEIGHTS as $h => $w) {
            array_push($hours, ...array_fill(0, $w, $h));
        }

        for ($back = 90; $back >= 1; $back--) {
            $day = $today->subDays($back);
            if (Order::query()->whereDate('business_date', $day->toDateString())->exists()) {
                continue;
            }

            DB::transaction(function () use ($day, $back, $branches, $variants, $customers, $hours, $unitCost): void {
                foreach ($branches as $b => $branchId) {
                    // Thursday/Friday busiest; the second branch is smaller; ~15% growth over the period.
                    $weekday = [0 => 1.0, 1 => 0.9, 2 => 0.9, 3 => 0.95, 4 => 1.25, 5 => 1.35, 6 => 1.05][$day->dayOfWeek];
                    $count = (int) round((($b === 0) ? 34 : 18) * $weekday * (1 + (90 - $back) / 600) * (0.85 + mt_rand(0, 30) / 100));

                    $placed = [];
                    for ($i = 0; $i < $count; $i++) {
                        $placed[] = $day->setTime($hours[mt_rand(0, count($hours) - 1)], mt_rand(0, 59));
                    }
                    sort($placed);

                    foreach ($placed as $n => $at) {
                        $roll = mt_rand(1, 100);
                        $type = $roll <= 35 ? 'dine_in' : ($roll <= 60 ? 'takeaway' : ($roll <= 80 ? 'qr_table' : 'delivery'));
                        $lines = [];
                        foreach (range(1, mt_rand(1, 3)) as $_) {
                            /** @var ProductVariant $v */
                            $v = $variants[mt_rand(0, $variants->count() - 1)];
                            $qty = mt_rand(1, 100) <= 80 ? 1 : 2;
                            $price = (int) $v->basePrice()?->amount;
                            $lines[] = ['product_id' => $v->product_id, 'variant_id' => $v->id, 'product_name' => $v->product->name, 'variant_name' => $v->name, 'unit_price' => $price, 'modifiers_total' => 0, 'quantity' => $qty, 'line_total' => $price * $qty];
                        }
                        $subtotal = array_sum(array_column($lines, 'line_total'));
                        $fee = $type === 'delivery' ? 300_000 : 0;
                        $cancelled = mt_rand(1, 100) <= 3;

                        $order = Order::query()->create([
                            'branch_id' => $branchId, 'business_date' => $day->toDateString(), 'daily_number' => $n + 1,
                            'customer_id' => $customers !== [] && mt_rand(1, 100) <= 45 ? $customers[mt_rand(0, count($customers) - 1)] : null,
                            'type' => $type, 'source' => $type === 'qr_table' ? 'qr' : 'web',
                            'status' => $cancelled ? 'cancelled' : 'completed', 'payment_status' => $cancelled ? 'unpaid' : 'paid',
                            'subtotal' => $subtotal, 'discount_total' => 0, 'delivery_fee' => $fee, 'total' => $subtotal + $fee,
                            'placed_at' => $at->utc(), 'completed_at' => $cancelled ? null : $at->addMinutes(25)->utc(),
                            'cancelled_at' => $cancelled ? $at->addMinutes(5)->utc() : null, 'cancel_reason' => $cancelled ? 'انصراف مشتری' : null,
                        ]);
                        foreach ($lines as $line) {
                            $item = OrderItem::query()->create(['order_id' => $order->id] + $line);
                            if (($unitCost[$line['variant_id']] ?? 0) > 0) {
                                OrderItemCost::query()->create(['order_id' => $order->id, 'order_item_id' => $item->id, 'cost' => $unitCost[$line['variant_id']] * $line['quantity'], 'breakdown' => []]);
                            }
                        }
                        if (! $cancelled) {
                            $pay = mt_rand(1, 100);
                            Payment::query()->create([
                                'order_id' => $order->id, 'status' => 'paid', 'amount' => $order->total,
                                'method' => $type === 'delivery' || $pay <= 30 ? 'online' : ($pay <= 55 ? 'cash' : 'card_pos'),
                                'paid_at' => $at->addMinutes(20)->utc(),
                            ]);
                        }
                    }
                }
            });
        }

        $rollup = app(RollupDay::class);
        foreach (MetricDirtyDay::query()->orderBy('business_date')->get() as $dirty) {
            $rollup->handle($dirty->business_date->toDateString());
        }
    }
}
