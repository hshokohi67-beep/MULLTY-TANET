<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Modifier;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Actions\Orders\PlaceOrder;
use App\Modules\Commerce\Actions\Orders\TransitionOrder;
use App\Modules\Commerce\Actions\Tables\ManageTableQr;
use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Data\CheckoutData;
use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Enums\TableRequestType;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\OrderSession;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\TenantSetting;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Payments\Actions\SyncOrderPaymentStatus;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use App\Support\Localization\PersianNumber;
use App\Support\Money\CurrencyUnit;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;

/**
 * Tables, a delivery zone, two discounts, a few live orders (some paid at the counter) and online payment for «کافه نمونه» ("sample cafe"). Runs inside the tenant.
 * The QR token of «میز ۱» ("table 1") is written to the log so a developer can open the table page locally.
 */
class CommerceDemoSeeder extends Seeder
{
    public function run(ManageTableQr $qr, TableSessions $sessions, PlaceOrder $place, TransitionOrder $transition, SyncOrderPaymentStatus $syncPayments): void
    {
        $t = fn (int $toman) => Money::fromUnit($toman, CurrencyUnit::Toman)->rials;
        $branch = Branch::query()->where('slug', 'main')->firstOrFail();

        $tables = collect(range(1, 6))->map(fn (int $i) => RestaurantTable::query()->create([
            'branch_id' => $branch->id,
            'label' => 'میز '.PersianNumber::toPersian((string) $i),
            'capacity' => $i <= 4 ? 2 : 4,
            'sort' => $i,
        ]));
        $token = $qr->issue($tables[0])['token'];
        logger()->info('[demo] QR token for میز ۱ (table 1): '.$token);

        DeliveryZone::query()->create([
            'branch_id' => $branch->id, 'name' => 'تا ۳ کیلومتر', 'type' => 'radius', 'radius_m' => 3000,
            'delivery_fee' => $t(30_000), 'free_delivery_min' => $t(300_000), 'min_order' => $t(80_000), 'eta_minutes' => 40,
        ]);

        Discount::query()->create([
            'name' => 'ساعت خوش عصر', 'kind' => 'percent', 'value' => 1000, 'applies_to' => 'order',
            'schedule' => ['weekdays' => [6, 7, 1, 2, 3], 'from' => '15:00', 'to' => '18:00'],
        ]);
        Discount::query()->create([
            'name' => 'شب یلدا', 'code' => 'YALDA', 'kind' => 'fixed', 'value' => $t(50_000), 'applies_to' => 'order',
            'min_order' => $t(300_000), 'usage_limit' => 200, 'per_customer_limit' => 1,
        ]);

        // A few orders in different states so the board isn't empty.
        $latte = Product::query()->where('name', 'لاته')->with('variants')->firstOrFail();
        $espresso = Product::query()->where('name', 'اسپرسو')->with('variants')->firstOrFail();
        $cake = Product::query()->where('name', 'چیزکیک نیویورکی')->with('variants')->firstOrFail();
        $regularMilk = Modifier::query()->where('name', 'شیر معمولی')->value('id');

        $session = $sessions->join($tables[1]);
        $sessions->request($session, TableRequestType::CallWaiter);

        // Online payment on (the local "fake" gateway approves everything; a real merchant ID goes in settings).
        TenantSetting::query()->create(['key' => 'payments.online.enabled', 'value' => '1']);

        $this->demoOrder($place, $transition, $syncPayments, $branch, 'demo-0', OrderType::QrTable, OrderSource::Qr,
            [[$latte, 1, [$regularMilk]], [$cake, 1, []]], session: $session, note: 'لطفاً کیک را گرم کنید');
        $this->demoOrder($place, $transition, $syncPayments, $branch, 'demo-1', OrderType::Counter, OrderSource::Dashboard,
            [[$espresso, 2, []]], steps: [OrderStatus::Accepted, OrderStatus::Preparing], paidWith: PaymentMethod::Cash);
        $this->demoOrder($place, $transition, $syncPayments, $branch, 'demo-2', OrderType::Takeaway, OrderSource::Dashboard,
            [[$latte, 2, [$regularMilk]]], steps: [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready], paidWith: PaymentMethod::CardPos, contactName: 'علی رضایی');
    }

    /**
     * @param  list<array{0: Product, 1: int, 2: list<string|null>}>  $lines
     * @param  list<OrderStatus>  $steps
     */
    private function demoOrder(
        PlaceOrder $place,
        TransitionOrder $transition,
        SyncOrderPaymentStatus $syncPayments,
        Branch $branch,
        string $key,
        OrderType $type,
        OrderSource $source,
        array $lines,
        ?OrderSession $session = null,
        ?string $note = null,
        array $steps = [],
        ?PaymentMethod $paidWith = null,
        ?string $contactName = null,
    ): void {
        $order = $place->handle(new CheckoutData(
            branch: $branch,
            type: $type,
            source: $source,
            lines: array_map(fn (array $l) => ['product_id' => $l[0]->id, 'variant_id' => $l[0]->variants->firstOrFail()->id, 'quantity' => $l[1], 'modifier_ids' => array_values(array_filter($l[2]))], $lines),
            idempotencyKey: $key,
            session: $session,
            contactName: $contactName,
            note: $note,
            actorType: 'system',
        ))['order'];

        foreach ($steps as $step) {
            $transition->handle($order, $step, 'system');
        }

        // Paid at the till (the table order is still open).
        if ($paidWith !== null) {
            Payment::query()->create([
                'order_id' => $order->id,
                'method' => $paidWith,
                'status' => PaymentAttemptStatus::Paid,
                'amount' => $order->total,
                'reference' => $paidWith === PaymentMethod::CardPos ? '402318' : null,
                'paid_at' => now(),
            ]);
            $syncPayments->handle($order);
        }
    }
}
