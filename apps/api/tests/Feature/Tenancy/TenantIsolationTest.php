<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\BillingPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Actions\SaveCategory;
use App\Modules\Catalog\Actions\SaveModifierGroup;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Commerce\Actions\Carts\ManageCart;
use App\Modules\Commerce\Actions\Orders\PlaceOrder;
use App\Modules\Commerce\Actions\Tables\ManageTableQr;
use App\Modules\Commerce\Actions\Tables\TableSessions;
use App\Modules\Commerce\Data\CheckoutData;
use App\Modules\Commerce\Enums\OrderSource;
use App\Modules\Commerce\Enums\OrderType;
use App\Modules\Commerce\Enums\TableRequestType;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Customers\Models\Customer;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\User;
use App\Modules\Insights\Models\ShiftNote;
use App\Modules\Inventory\Models\Ingredient;
use App\Modules\Inventory\Models\PurchaseOrder;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Kitchen\Actions\KitchenDevices;
use App\Modules\Kitchen\Actions\RouteOrderToKitchen;
use App\Modules\Kitchen\Models\KitchenItem;
use App\Modules\Kitchen\Models\KitchenStation;
use App\Modules\Loyalty\Actions\PostWalletTransaction;
use App\Modules\Loyalty\Enums\WalletTransactionType;
use App\Modules\Loyalty\Models\CashbackRule;
use App\Modules\Loyalty\Models\LoyaltyTier;
use App\Modules\Loyalty\Models\Wallet;
use App\Modules\Messaging\Models\SmsCampaign;
use App\Modules\Operations\Models\AttendanceRecord;
use App\Modules\Operations\Models\Employee;
use App\Modules\Operations\Models\Expense;
use App\Modules\Operations\Models\ExpenseCategory;
use App\Modules\Operations\Models\Shift;
use App\Modules\Payments\Enums\PaymentAttemptStatus;
use App\Modules\Payments\Enums\PaymentMethod;
use App\Modules\Payments\Models\Payment;
use App\Modules\Storefront\Models\Story;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The reusable cross-tenant isolation harness. Every tenant-scoped endpoint added
 * in later phases must be listed in one of the providers below.
 *
 * Rule 1: Tenant A's staff cannot open Tenant B (403, no data).
 * Rule 2: Inside their own tenant, B's record IDs don't exist (404, not 403), and nothing of B changes.
 * Rule 3: Lists never contain B's rows.
 * Rule 4: B's IDs inside request payloads fail validation.
 */
final class TenantIsolationTest extends TestCase
{
    private Tenant $a;

    private Tenant $b;

    private User $ownerA;

    private User $ownerB;

    private Branch $branchB;

    private TenantUser $memberB;

    /** @var array{product: string, category: string, group: string, image: string} */
    private array $catalogB;

    protected function setUp(): void
    {
        parent::setUp();

        ['tenant' => $this->a, 'owner' => $this->ownerA] = $this->createTenantWithOwner('cafe-a');
        ['tenant' => $this->b, 'owner' => $this->ownerB] = $this->createTenantWithOwner('cafe-b');

        $this->branchB = $this->inTenant($this->b, fn () => Branch::query()->where('slug', 'main')->firstOrFail());
        $this->memberB = $this->inTenant($this->b, fn () => TenantUser::query()->firstOrFail());

        $this->catalogB = $this->inTenant($this->b, function (): array {
            $category = app(SaveCategory::class)->handle(['name' => 'دسته ب']);
            $product = app(SaveProduct::class)->handle(
                new ProductData(name: 'محصول ب', categoryIds: [$category->id]),
                null,
                [new VariantData(null, null, 500_000)],
            );
            $group = app(SaveModifierGroup::class)->handle(['name' => 'گروه ب', 'min_select' => 0, 'max_select' => 0], [['name' => 'x', 'price_delta' => 0]]);
            $image = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'x.png', 'sort' => 0]);

            return ['product' => $product->id, 'category' => $category->id, 'group' => $group->id, 'image' => $image->id];
        });

        $this->commerceB = $this->inTenant($this->b, function (): array {
            $table = RestaurantTable::query()->create(['branch_id' => $this->branchB->id, 'label' => 'میز ب']);
            $qr = app(ManageTableQr::class)->issue($table)['token'];
            $session = app(TableSessions::class)->join($table);
            $request = app(TableSessions::class)->request($session, TableRequestType::CallWaiter);
            $zone = DeliveryZone::query()->create(['branch_id' => $this->branchB->id, 'name' => 'ب', 'radius_m' => 1000]);
            $discount = Discount::query()->create(['name' => 'ب', 'code' => 'BONLY', 'kind' => 'fixed', 'value' => 1, 'applies_to' => 'order']);
            ['cart' => $cart, 'token' => $cartToken] = app(ManageCart::class)->create($this->branchB, OrderType::QrTable, $session);
            app(ManageCart::class)->add($cart, Product::query()->findOrFail($this->catalogB['product'])->variants()->value('id'), 1, [], null);
            $order = app(PlaceOrder::class)->handle(new CheckoutData(
                branch: $this->branchB,
                type: OrderType::QrTable,
                source: OrderSource::Qr,
                lines: app(ManageCart::class)->lines($cart->refresh()->load('items')),
                idempotencyKey: 'b-1',
                session: $session,
            ))['order'];
            $customer = Customer::query()->create(['phone_e164' => '+989127777777', 'name' => 'مشتری ب']);
            app(PostWalletTransaction::class)->handle($customer, WalletTransactionType::Adjustment, 500_000);
            $tier = LoyaltyTier::query()->create(['name' => 'ب', 'min_spend' => 0]);
            $rule = CashbackRule::query()->create(['name' => 'ب', 'kind' => 'fixed', 'value' => 1, 'min_spend' => 0]);
            $station = KitchenStation::query()->create(['branch_id' => $this->branchB->id, 'name' => 'بار ب', 'is_default' => true]);
            app(RouteOrderToKitchen::class)->handle($order);
            $kitchenItem = KitchenItem::query()->where('order_id', $order->id)->value('id');
            ['device' => $device, 'code' => $pairingCode] = app(KitchenDevices::class)->create(['branch_id' => $this->branchB->id, 'name' => 'تبلت ب']);
            $deviceToken = app(KitchenDevices::class)->pair($pairingCode)['token'];
            $note = ShiftNote::query()->create(['body' => 'یادداشت ب', 'author_id' => $this->ownerB->id]);
            $ingredient = Ingredient::query()->create(['name' => 'قهوه ب', 'unit' => 'g', 'avg_cost' => 1000]);
            $supplier = Supplier::query()->create(['name' => 'تأمین‌کننده ب']);
            $purchase = PurchaseOrder::query()->create(['supplier_id' => $supplier->id, 'branch_id' => $this->branchB->id, 'number' => 1, 'status' => 'ordered', 'total' => 5000]);
            $purchase->items()->create(['ingredient_id' => $ingredient->id, 'quantity' => 1000, 'unit_price' => 5000, 'line_total' => 5000]);
            $employee = Employee::query()->create(['name' => 'کارمند ب', 'branch_id' => $this->branchB->id, 'user_id' => $this->ownerB->id, 'pay_type' => 'hourly', 'rate' => 1000]);
            $shift = Shift::query()->create(['employee_id' => $employee->id, 'branch_id' => $this->branchB->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(8)]);
            $attendance = AttendanceRecord::query()->create(['employee_id' => $employee->id, 'branch_id' => $this->branchB->id, 'clock_in_at' => now()->subHour(), 'source' => 'self']);
            $expenseCategory = ExpenseCategory::query()->create(['name' => 'دسته ب']);
            $expense = Expense::query()->create(['branch_id' => $this->branchB->id, 'category_id' => $expenseCategory->id, 'amount' => 5000, 'spent_on' => now()->toDateString(), 'method' => 'cash']);
            $invoice = BillingInvoice::query()->create([
                'number' => '1405-900001', 'kind' => 'checkout', 'status' => 'open', 'plan_id' => Plan::query()->where('key', 'pro')->value('id'), 'cycle' => 'monthly',
                'addons' => [], 'mode' => 'pay_now', 'lines' => [], 'subtotal' => 1000, 'credit' => 0, 'vat_rate' => 10, 'vat' => 100, 'total' => 1100,
            ]);
            $adCampaign = AdCampaign::query()->forceCreate([
                'name' => 'کمپین ب', 'placement' => 'search_top', 'status' => 'approved', 'start_date' => now()->addDay()->toDateString(), 'days' => 3,
                'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(4), 'cities' => [], 'headline' => 'تبلیغ ب', 'cta' => 'menu', 'daily_price' => 900_000, 'amount' => 2_700_000,
                'ref' => 'bbbbbbbbbbbbbbbb',
            ]);
            $smsCampaign = SmsCampaign::query()->create(['name' => 'کمپین ب', 'body' => 'پیام ب', 'audience' => [], 'status' => 'draft']);
            $adInvoice = BillingInvoice::query()->create([
                'number' => '1405-900002', 'kind' => 'ad', 'subject_id' => $adCampaign->id, 'status' => 'open',
                'addons' => [], 'lines' => [], 'subtotal' => 2_700_000, 'credit' => 0, 'vat_rate' => 10, 'vat' => 270_000, 'total' => 2_970_000,
            ]);
            $story = Story::query()->create(['image_path' => 'b/s.webp', 'thumb_path' => 'b/t.webp', 'width' => 900, 'height' => 1600, 'caption' => 'استوری ب', 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
            $payment = Payment::query()->create(['order_id' => $order->id, 'method' => PaymentMethod::Online, 'gateway' => 'fake', 'status' => PaymentAttemptStatus::Pending, 'amount' => $order->total, 'authority' => 'FAKEBONLY']);

            return [
                'table' => $table->id, 'qr' => $qr, 'session' => $session->accessToken(), 'request' => $request->id,
                'zone' => $zone->id, 'discount' => $discount->id, 'cart' => $cartToken, 'order' => $order->id,
                'tracking' => $order->trackingToken(), 'payment' => $payment->id,
                'customer' => $customer->id, 'tier' => $tier->id, 'rule' => $rule->id,
                'station' => $station->id, 'kitchen_item' => (string) $kitchenItem, 'device' => $device->id, 'device_token' => $deviceToken, 'note' => $note->id, 'story' => $story->id,
                'ingredient' => $ingredient->id, 'supplier' => $supplier->id, 'purchase' => $purchase->id,
                'invoice' => $invoice->id, 'ad_campaign' => $adCampaign->id, 'ad_invoice' => $adInvoice->id, 'sms_campaign' => $smsCampaign->id, 'employee' => $employee->id, 'shift' => $shift->id, 'attendance' => $attendance->id, 'expense_category' => $expenseCategory->id, 'expense' => $expense->id,
            ];
        });
    }

    /** @var array<string, string> */
    private array $commerceB;

    /** @return array<string, array{string, string}> */
    public static function tenantEndpoints(): array
    {
        return [
            'tenant profile' => ['GET', '/api/v1/tenant'],
            'stories' => ['GET', '/api/v1/stories'],
            'ingredients' => ['GET', '/api/v1/inventory/ingredients'],
            'expense categories' => ['GET', '/api/v1/expense-categories'],
            'create expense category' => ['POST', '/api/v1/expense-categories'],
            'expenses' => ['GET', '/api/v1/expenses'],
            'create expense' => ['POST', '/api/v1/expenses'],
            'expense summary' => ['GET', '/api/v1/expenses/summary'],
            'employees' => ['GET', '/api/v1/staff/employees'],
            'create employee' => ['POST', '/api/v1/staff/employees'],
            'shifts' => ['GET', '/api/v1/staff/shifts'],
            'create shift' => ['POST', '/api/v1/staff/shifts'],
            'copy week' => ['POST', '/api/v1/staff/shifts/copy-week'],
            'attendance' => ['GET', '/api/v1/staff/attendance'],
            'create attendance' => ['POST', '/api/v1/staff/attendance'],
            'payroll' => ['GET', '/api/v1/staff/payroll'],
            'time clock' => ['GET', '/api/v1/time-clock/me'],
            'clock in' => ['POST', '/api/v1/time-clock/in'],
            'clock out' => ['POST', '/api/v1/time-clock/out'],
            'report summary' => ['GET', '/api/v1/reports/summary'],
            'report products' => ['GET', '/api/v1/reports/products'],
            'report hours' => ['GET', '/api/v1/reports/hours'],
            'report branches' => ['GET', '/api/v1/reports/branches'],
            'report customers' => ['GET', '/api/v1/reports/customers'],
            'report inventory' => ['GET', '/api/v1/reports/inventory'],
            'report export' => ['GET', '/api/v1/reports/export'],
            'marketplace listing' => ['GET', '/api/v1/marketplace/listing'],
            'update marketplace listing' => ['PUT', '/api/v1/marketplace/listing'],
            'billing status' => ['GET', '/api/v1/billing/status'],
            'billing' => ['GET', '/api/v1/billing'],
            'billing plans' => ['GET', '/api/v1/billing/plans'],
            'billing quote' => ['POST', '/api/v1/billing/quote'],
            'billing checkout' => ['POST', '/api/v1/billing/checkout'],
            'billing invoices' => ['GET', '/api/v1/billing/invoices'],
            'billing cancel' => ['POST', '/api/v1/billing/cancel'],
            'billing resume' => ['POST', '/api/v1/billing/resume'],
            'ads' => ['GET', '/api/v1/ads'],
            'sms centre' => ['GET', '/api/v1/sms'],
            'sms account' => ['PUT', '/api/v1/sms/account'],
            'sms test' => ['POST', '/api/v1/sms/account/test'],
            'sms templates' => ['PUT', '/api/v1/sms/templates'],
            'sms logs' => ['GET', '/api/v1/sms/logs'],
            'sms audience' => ['POST', '/api/v1/sms/audience'],
            'create sms campaign' => ['POST', '/api/v1/sms/campaigns'],
            'ads quote' => ['POST', '/api/v1/ads/quote'],
            'create ad campaign' => ['POST', '/api/v1/ads/campaigns'],
            'create ingredient' => ['POST', '/api/v1/inventory/ingredients'],
            'stock movements' => ['GET', '/api/v1/inventory/movements'],
            'stock adjustment' => ['POST', '/api/v1/inventory/adjustments'],
            'stock count' => ['POST', '/api/v1/inventory/counts'],
            'suppliers' => ['GET', '/api/v1/inventory/suppliers'],
            'create supplier' => ['POST', '/api/v1/inventory/suppliers'],
            'purchases' => ['GET', '/api/v1/inventory/purchases'],
            'create purchase' => ['POST', '/api/v1/inventory/purchases'],
            'dashboard search' => ['GET', '/api/v1/dashboard/search?q=x'],
            'dashboard alerts' => ['GET', '/api/v1/dashboard/alerts'],
            'dashboard setup' => ['GET', '/api/v1/dashboard/setup'],
            'skip setup step' => ['POST', '/api/v1/dashboard/setup/skip'],
            'reorder stories' => ['PUT', '/api/v1/stories/order'],
            'upload cover' => ['POST', '/api/v1/tenant/branding/cover'],
            'delete cover' => ['DELETE', '/api/v1/tenant/branding/cover'],
            'upload logo' => ['POST', '/api/v1/tenant/branding/logo'],
            'permissions' => ['GET', '/api/v1/permissions'],
            'update tenant' => ['PATCH', '/api/v1/tenant'],
            'branding' => ['GET', '/api/v1/tenant/branding'],
            'update branding' => ['PATCH', '/api/v1/tenant/branding'],
            'settings' => ['GET', '/api/v1/tenant/settings'],
            'update settings' => ['PATCH', '/api/v1/tenant/settings'],
            'branches' => ['GET', '/api/v1/branches'],
            'create branch' => ['POST', '/api/v1/branches'],
            'team' => ['GET', '/api/v1/team'],
            'add member' => ['POST', '/api/v1/team'],
            'roles' => ['GET', '/api/v1/roles'],
            'audit logs' => ['GET', '/api/v1/audit-logs'],
            'categories' => ['GET', '/api/v1/catalog/categories'],
            'create category' => ['POST', '/api/v1/catalog/categories'],
            'products' => ['GET', '/api/v1/catalog/products'],
            'create product' => ['POST', '/api/v1/catalog/products'],
            'quick add' => ['POST', '/api/v1/catalog/products/quick'],
            'modifier groups' => ['GET', '/api/v1/catalog/modifier-groups'],
            'create modifier group' => ['POST', '/api/v1/catalog/modifier-groups'],
            'bulk prices' => ['POST', '/api/v1/catalog/prices/bulk'],
            'price history' => ['GET', '/api/v1/catalog/price-history'],
            'tables' => ['GET', '/api/v1/tables'],
            'create table' => ['POST', '/api/v1/tables'],
            'table requests' => ['GET', '/api/v1/table-requests'],
            'delivery zones' => ['GET', '/api/v1/delivery-zones'],
            'create delivery zone' => ['POST', '/api/v1/delivery-zones'],
            'delivery check' => ['POST', '/api/v1/delivery-zones/check'],
            'orders' => ['GET', '/api/v1/orders'],
            'orders summary' => ['GET', '/api/v1/orders/summary'],
            'orders live version' => ['GET', '/api/v1/orders/live-version'],
            'dashboard overview' => ['GET', '/api/v1/dashboard/overview'],
            'dashboard layout' => ['GET', '/api/v1/dashboard/layout'],
            'save dashboard layout' => ['PUT', '/api/v1/dashboard/layout'],
            'reset dashboard layout' => ['DELETE', '/api/v1/dashboard/layout'],
            'dashboard widget' => ['GET', '/api/v1/dashboard/widgets/goal'],
            'post shift note' => ['POST', '/api/v1/dashboard/shift-notes'],
            'create staff order' => ['POST', '/api/v1/orders'],
            'discounts' => ['GET', '/api/v1/discounts'],
            'create discount' => ['POST', '/api/v1/discounts'],
            'payments' => ['GET', '/api/v1/payments'],
            'payments summary' => ['GET', '/api/v1/payments/summary'],
            'customers' => ['GET', '/api/v1/customers'],
            'customers export' => ['GET', '/api/v1/customers/export'],
            'loyalty program' => ['GET', '/api/v1/loyalty/program'],
            'update loyalty program' => ['PUT', '/api/v1/loyalty/program'],
            'create tier' => ['POST', '/api/v1/loyalty/tiers'],
            'create cashback rule' => ['POST', '/api/v1/loyalty/cashback-rules'],
            'kitchen setup' => ['GET', '/api/v1/kitchen/setup'],
            'create station' => ['POST', '/api/v1/kitchen/stations'],
            'create kitchen device' => ['POST', '/api/v1/kitchen/devices'],
        ];
    }

    #[DataProvider('tenantEndpoints')]
    public function test_staff_of_tenant_a_cannot_open_tenant_b(string $method, string $uri): void
    {
        $this->json($method, $uri, [], $this->staffHeaders($this->ownerA, $this->b))
            ->assertForbidden()
            ->assertJsonPath('code', 'not_a_member')
            ->assertJsonMissingPath('data');
    }

    /** @return array<string, array{string, string}> */
    public static function recordEndpoints(): array
    {
        return [
            'show branch' => ['GET', '/api/v1/branches/{branch}'],
            'update branch' => ['PUT', '/api/v1/branches/{branch}'],
            'branch hours' => ['PUT', '/api/v1/branches/{branch}/opening-hours'],
            'branch status' => ['GET', '/api/v1/branches/{branch}/open-status'],
            'member roles' => ['PUT', '/api/v1/team/{member}/roles'],
            'update category' => ['PUT', '/api/v1/catalog/categories/{category}'],
            'delete category' => ['DELETE', '/api/v1/catalog/categories/{category}'],
            'show product' => ['GET', '/api/v1/catalog/products/{product}'],
            'update product' => ['PUT', '/api/v1/catalog/products/{product}'],
            'delete product' => ['DELETE', '/api/v1/catalog/products/{product}'],
            'product variants' => ['PUT', '/api/v1/catalog/products/{product}/variants'],
            'product branch prices' => ['PUT', '/api/v1/catalog/products/{product}/branch-prices'],
            'product modifier groups' => ['PUT', '/api/v1/catalog/products/{product}/modifier-groups'],
            'product availability' => ['PUT', '/api/v1/catalog/products/{product}/availability'],
            'product image upload' => ['POST', '/api/v1/catalog/products/{product}/images'],
            'product image delete' => ['DELETE', '/api/v1/catalog/products/{product}/images/{image}'],
            'update modifier group' => ['PUT', '/api/v1/catalog/modifier-groups/{group}'],
            'delete modifier group' => ['DELETE', '/api/v1/catalog/modifier-groups/{group}'],
            'update table' => ['PUT', '/api/v1/tables/{table}'],
            'issue table qr' => ['POST', '/api/v1/tables/{table}/qr'],
            'close table session' => ['POST', '/api/v1/tables/{table}/close-session'],
            'acknowledge table request' => ['POST', '/api/v1/table-requests/{request}/acknowledge'],
            'update delivery zone' => ['PUT', '/api/v1/delivery-zones/{zone}'],
            'delete delivery zone' => ['DELETE', '/api/v1/delivery-zones/{zone}'],
            'show order' => ['GET', '/api/v1/orders/{order}'],
            'order status' => ['POST', '/api/v1/orders/{order}/status'],
            'update discount' => ['PUT', '/api/v1/discounts/{discount}'],
            'delete discount' => ['DELETE', '/api/v1/discounts/{discount}'],
            'order payments' => ['GET', '/api/v1/orders/{order}/payments'],
            'record payment' => ['POST', '/api/v1/orders/{order}/payments'],
            'refund payment' => ['POST', '/api/v1/payments/{payment}/refunds'],
            'show customer' => ['GET', '/api/v1/customers/{customer}'],
            'update customer' => ['PATCH', '/api/v1/customers/{customer}'],
            'customer wallet ledger' => ['GET', '/api/v1/customers/{customer}/wallet-transactions'],
            'customer points ledger' => ['GET', '/api/v1/customers/{customer}/points-transactions'],
            'wallet adjustment' => ['POST', '/api/v1/customers/{customer}/wallet-adjustments'],
            'points adjustment' => ['POST', '/api/v1/customers/{customer}/points-adjustments'],
            'staff wallet payment' => ['POST', '/api/v1/orders/{order}/wallet-payment'],
            'update tier' => ['PUT', '/api/v1/loyalty/tiers/{tier}'],
            'delete tier' => ['DELETE', '/api/v1/loyalty/tiers/{tier}'],
            'update cashback rule' => ['PUT', '/api/v1/loyalty/cashback-rules/{rule}'],
            'delete cashback rule' => ['DELETE', '/api/v1/loyalty/cashback-rules/{rule}'],
            'delete shift note' => ['DELETE', '/api/v1/dashboard/shift-notes/{note}'],
            'update station' => ['PUT', '/api/v1/kitchen/stations/{station}'],
            'delete station' => ['DELETE', '/api/v1/kitchen/stations/{station}'],
            'station routing' => ['PUT', '/api/v1/kitchen/stations/{station}/products'],
            'repair device' => ['POST', '/api/v1/kitchen/devices/{device}/repair'],
            'revoke device' => ['POST', '/api/v1/kitchen/devices/{device}/revoke'],
            'kds start item' => ['POST', '/api/v1/kds/items/{kitchenItem}/start'],
            'kds ready item' => ['POST', '/api/v1/kds/items/{kitchenItem}/ready'],
            'kds recall item' => ['POST', '/api/v1/kds/items/{kitchenItem}/recall'],
            'kds bump order' => ['POST', '/api/v1/kds/orders/{order}/bump'],
            'kds acknowledge call' => ['POST', '/api/v1/kds/table-requests/{request}/acknowledge'],
            'category image' => ['POST', '/api/v1/catalog/categories/{category}/image'],
            'delete category image' => ['DELETE', '/api/v1/catalog/categories/{category}/image'],
            'update story' => ['POST', '/api/v1/stories/{story}'],
            'update ingredient' => ['PUT', '/api/v1/inventory/ingredients/{ingredient}'],
            'show invoice' => ['GET', '/api/v1/billing/invoices/{invoice}'],
            'pay invoice' => ['POST', '/api/v1/billing/invoices/{invoice}/pay'],
            'verify invoice' => ['POST', '/api/v1/billing/invoices/{invoice}/verify'],
            'update expense category' => ['PUT', '/api/v1/expense-categories/{expenseCategory}'],
            'delete expense category' => ['DELETE', '/api/v1/expense-categories/{expenseCategory}'],
            'update expense' => ['PUT', '/api/v1/expenses/{expense}'],
            'delete expense' => ['DELETE', '/api/v1/expenses/{expense}'],
            'update employee' => ['PUT', '/api/v1/staff/employees/{employee}'],
            'update shift' => ['PUT', '/api/v1/staff/shifts/{shift}'],
            'delete shift' => ['DELETE', '/api/v1/staff/shifts/{shift}'],
            'update attendance' => ['PUT', '/api/v1/staff/attendance/{attendance}'],
            'delete attendance' => ['DELETE', '/api/v1/staff/attendance/{attendance}'],
            'delete ingredient' => ['DELETE', '/api/v1/inventory/ingredients/{ingredient}'],
            'update supplier' => ['PUT', '/api/v1/inventory/suppliers/{supplier}'],
            'show purchase' => ['GET', '/api/v1/inventory/purchases/{purchase}'],
            'update purchase' => ['PUT', '/api/v1/inventory/purchases/{purchase}'],
            'order purchase' => ['POST', '/api/v1/inventory/purchases/{purchase}/order'],
            'receive purchase' => ['POST', '/api/v1/inventory/purchases/{purchase}/receive'],
            'cancel purchase' => ['POST', '/api/v1/inventory/purchases/{purchase}/cancel'],
            'pay purchase' => ['POST', '/api/v1/inventory/purchases/{purchase}/payments'],
            'product recipe' => ['GET', '/api/v1/catalog/products/{product}/recipe'],
            'save recipe' => ['PUT', '/api/v1/catalog/products/{product}/recipe'],
            'delete story' => ['DELETE', '/api/v1/stories/{story}'],
            'show ad campaign' => ['GET', '/api/v1/ads/campaigns/{adCampaign}'],
            'update ad campaign' => ['PUT', '/api/v1/ads/campaigns/{adCampaign}'],
            'ad campaign image' => ['POST', '/api/v1/ads/campaigns/{adCampaign}/image'],
            'delete ad campaign image' => ['DELETE', '/api/v1/ads/campaigns/{adCampaign}/image'],
            'submit ad campaign' => ['POST', '/api/v1/ads/campaigns/{adCampaign}/submit'],
            'cancel ad campaign' => ['POST', '/api/v1/ads/campaigns/{adCampaign}/cancel'],
            'pay ad campaign' => ['POST', '/api/v1/ads/campaigns/{adCampaign}/pay'],
            'verify ad invoice' => ['POST', '/api/v1/ads/invoices/{adInvoice}/verify'],
            'update sms campaign' => ['PUT', '/api/v1/sms/campaigns/{smsCampaign}'],
            'schedule sms campaign' => ['POST', '/api/v1/sms/campaigns/{smsCampaign}/schedule'],
            'cancel sms campaign' => ['POST', '/api/v1/sms/campaigns/{smsCampaign}/cancel'],
        ];
    }

    #[DataProvider('recordEndpoints')]
    public function test_foreign_record_ids_do_not_exist_inside_own_tenant(string $method, string $uri): void
    {
        $uri = str_replace(
            ['{branch}', '{member}', '{category}', '{product}', '{group}', '{image}', '{table}', '{request}', '{zone}', '{order}', '{discount}', '{payment}', '{customer}', '{tier}', '{rule}', '{station}', '{kitchenItem}', '{device}', '{note}', '{story}', '{ingredient}', '{supplier}', '{purchase}', '{invoice}', '{employee}', '{shift}', '{attendance}', '{expenseCategory}', '{expense}', '{adCampaign}', '{adInvoice}', '{smsCampaign}'],
            [$this->branchB->id, $this->memberB->id, $this->catalogB['category'], $this->catalogB['product'], $this->catalogB['group'], $this->catalogB['image'],
                $this->commerceB['table'], $this->commerceB['request'], $this->commerceB['zone'], $this->commerceB['order'], $this->commerceB['discount'], $this->commerceB['payment'], $this->commerceB['customer'], $this->commerceB['tier'], $this->commerceB['rule'], $this->commerceB['station'], $this->commerceB['kitchen_item'], $this->commerceB['device'], $this->commerceB['note'], $this->commerceB['story'], $this->commerceB['ingredient'], $this->commerceB['supplier'], $this->commerceB['purchase'], $this->commerceB['invoice'], $this->commerceB['employee'], $this->commerceB['shift'], $this->commerceB['attendance'], $this->commerceB['expense_category'], $this->commerceB['expense'], $this->commerceB['ad_campaign'], $this->commerceB['ad_invoice'], $this->commerceB['sms_campaign']],
            $uri,
        );

        $this->json($method, $uri, ['name' => 'x', 'slug' => 'x', 'intervals' => [], 'role_ids' => ['x'], 'amount' => 1000, 'reason' => 'x', 'idempotency_key' => 'x', 'min_spend' => 5, 'kind' => 'fixed', 'value' => 5, 'station_id' => $this->commerceB['station'], 'product_ids' => []], $this->staffHeaders($this->ownerA, $this->a))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        // And nothing changed on B's side.
        $this->inTenant($this->b, function (): void {
            $this->assertSame('شعبه مرکزی', $this->branchB->fresh()?->name);
            $product = Product::query()->with('variants.prices', 'images')->findOrFail($this->catalogB['product']);
            $this->assertSame('محصول ب', $product->name);
            $this->assertSame(500_000, $product->variants->sole()->prices->sole()->amount);
            $this->assertCount(1, $product->images);
            $this->assertTrue(ModifierGroup::query()->whereKey($this->catalogB['group'])->exists());
            $this->assertTrue(Category::query()->whereKey($this->catalogB['category'])->exists());
            $this->assertSame('placed', Order::query()->findOrFail($this->commerceB['order'])->status->value);
            $this->assertTrue(DeliveryZone::query()->whereKey($this->commerceB['zone'])->exists());
            $this->assertTrue((bool) Discount::query()->whereKey($this->commerceB['discount'])->value('is_active'));
            $this->assertSame('میز ب', RestaurantTable::query()->findOrFail($this->commerceB['table'])->label);
            $this->assertSame(0, Payment::query()->where('order_id', $this->commerceB['order'])->where('status', PaymentAttemptStatus::Paid)->count());
            $this->assertSame(500_000, Wallet::query()->where('customer_id', $this->commerceB['customer'])->value('balance'));
            $this->assertSame('مشتری ب', Customer::query()->findOrFail($this->commerceB['customer'])->name);
            $this->assertSame(0, LoyaltyTier::query()->findOrFail($this->commerceB['tier'])->min_spend);
            $this->assertSame(1, CashbackRule::query()->findOrFail($this->commerceB['rule'])->value);
            $this->assertSame('queued', KitchenItem::query()->findOrFail($this->commerceB['kitchen_item'])->status->value);
            $this->assertSame('بار ب', KitchenStation::query()->findOrFail($this->commerceB['station'])->name);
            $this->assertTrue(ShiftNote::query()->whereKey($this->commerceB['note'])->exists());
            $this->assertSame('استوری ب', Story::query()->findOrFail($this->commerceB['story'])->caption);
            $this->assertSame('قهوه ب', Ingredient::query()->findOrFail($this->commerceB['ingredient'])->name);
            $this->assertSame('ordered', PurchaseOrder::query()->findOrFail($this->commerceB['purchase'])->status);
            $this->assertSame(0, PurchaseOrder::query()->findOrFail($this->commerceB['purchase'])->paid_total);
            $this->assertSame('کارمند ب', Employee::query()->findOrFail($this->commerceB['employee'])->name);
            $this->assertSame('open', BillingInvoice::query()->findOrFail($this->commerceB['invoice'])->status);
            $this->assertSame(0, BillingPayment::query()->count());
            $this->assertTrue(Shift::query()->whereKey($this->commerceB['shift'])->exists());
            $this->assertNull(AttendanceRecord::query()->findOrFail($this->commerceB['attendance'])->clock_out_at);
            $this->assertSame(5000, Expense::query()->findOrFail($this->commerceB['expense'])->amount);
            $this->assertSame('approved', AdCampaign::query()->findOrFail($this->commerceB['ad_campaign'])->status);
            $this->assertSame('open', BillingInvoice::query()->findOrFail($this->commerceB['ad_invoice'])->status);
            $this->assertSame('draft', SmsCampaign::query()->findOrFail($this->commerceB['sms_campaign'])->status);
        });
    }

    public function test_lists_contain_only_own_tenant_rows(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);

        $branchIds = collect($this->getJson('/api/v1/branches', $headers)->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($this->branchB->id, $branchIds);

        $memberUserIds = collect($this->getJson('/api/v1/team', $headers)->assertOk()->json('data'))->pluck('user.id');
        $this->assertNotContains($this->ownerB->id, $memberUserIds);

        $auditTenantActions = collect($this->getJson('/api/v1/audit-logs', $headers)->assertOk()->json('data'))->pluck('subject_id');
        $this->assertNotContains($this->b->id, $auditTenantActions);

        $this->assertSame([], $this->getJson('/api/v1/catalog/products', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/catalog/categories', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/catalog/modifier-groups', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/catalog/price-history', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/public/menu', ['X-Tenant' => $this->a->slug])->assertOk()->json('data.products'));
        $this->assertSame([], $this->getJson('/api/v1/orders', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/tables', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/table-requests', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/delivery-zones', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/discounts', $headers)->assertOk()->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/payments', $headers)->assertOk()->json('data'));
        $this->assertSame(0, collect($this->getJson('/api/v1/payments/summary', $headers)->assertOk()->json('data.methods'))->sum('count'));
        $this->assertSame([], $this->getJson('/api/v1/customers', $headers)->assertOk()->json('data'));
        $this->assertStringNotContainsString('09127777777', $this->get('/api/v1/customers/export', $headers)->assertOk()->streamedContent());
        $this->assertSame([], $this->getJson('/api/v1/loyalty/program', $headers)->assertOk()->json('data.tiers'));
        $this->assertSame([], $this->getJson('/api/v1/dashboard/widgets/shift_notes', $headers)->assertOk()->json('data.notes'));
        $this->assertSame([], $this->getJson('/api/v1/dashboard/widgets/branches', $headers)->assertOk()->json('data.branches.1') ?? []);
    }

    public function test_storefront_tokens_of_tenant_b_are_worthless_at_tenant_a(): void
    {
        $a = ['X-Tenant' => $this->a->slug, 'Accept' => 'application/json'];

        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->commerceB['qr']], $a)->assertNotFound();
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], [...$a, 'X-Table-Session' => $this->commerceB['session']])->assertStatus(410);
        $this->getJson('/api/v1/public/cart', [...$a, 'X-Cart-Token' => $this->commerceB['cart']])->assertNotFound();
        $this->postJson('/api/v1/public/checkout', [], [...$a, 'X-Cart-Token' => $this->commerceB['cart'], 'Idempotency-Key' => 'x'])->assertNotFound();
        $this->getJson("/api/v1/public/orders/{$this->commerceB['order']}?token={$this->commerceB['tracking']}", $a)->assertNotFound();
        $this->postJson("/api/v1/public/orders/{$this->commerceB['order']}/pay", [], [...$a, 'X-Order-Token' => $this->commerceB['tracking']])->assertNotFound();
        $this->postJson("/api/v1/public/payments/{$this->commerceB['payment']}/verify", ['authority' => 'FAKEBONLY'], $a)->assertNotFound();

        // B's kitchen tablet is useless at A, and A's staff can't open B's kitchen.
        $this->getJson('/api/v1/kds/board', [...$a, 'Authorization' => 'Bearer '.$this->commerceB['device_token']])->assertUnauthorized();
        $this->getJson('/api/v1/kds/board', $this->staffHeaders($this->ownerA, $this->b))->assertForbidden();
        $this->assertSame([], $this->getJson('/api/v1/kds/board', $this->staffHeaders($this->ownerA, $this->a))->json('data.orders') ?? []);
        $this->assertSame('pending', $this->inTenant($this->b, fn () => Payment::query()->findOrFail($this->commerceB['payment'])->status->value));

        // Tenant B's coupon means nothing at tenant A.
        $branchA = $this->inTenant($this->a, fn () => Branch::query()->firstOrFail());
        $table = $this->inTenant($this->a, fn () => RestaurantTable::query()->create(['branch_id' => $branchA->id, 'label' => 'الف']));
        $qr = $this->inTenant($this->a, fn () => app(ManageTableQr::class)->issue($table)['token']);
        $session = $this->postJson('/api/v1/public/tables/session', ['qr_token' => $qr], $a)->json('data.session_token');
        $cart = $this->postJson('/api/v1/public/carts', ['order_type' => 'qr_table'], [...$a, 'X-Table-Session' => $session])->assertCreated()->json('data.cart_token');

        // And B's product variant can't be put in A's cart.
        $variantB = $this->inTenant($this->b, fn () => Product::query()->findOrFail($this->catalogB['product'])->variants()->value('id'));
        $this->postJson('/api/v1/public/cart/items', ['variant_id' => $variantB, 'quantity' => 1], [...$a, 'X-Cart-Token' => $cart])
            ->assertUnprocessable()->assertJsonValidationErrors('variant_id');

        $this->getJson('/api/v1/public/cart?coupon_code=BONLY', [...$a, 'X-Cart-Token' => $cart])->assertOk()->assertJsonPath('data.quote.discount', null);

        // Phase 7 storefront: A's shell never lists B's branches; B's branch can't be zone-checked at A.
        $shell = $this->getJson('/api/v1/public/storefront', $a)->assertOk();
        $this->assertNotContains($this->branchB->id, array_column($shell->json('data.branches'), 'id'));
        $this->postJson('/api/v1/public/delivery/check', ['branch_id' => $this->branchB->id, 'latitude' => 35.7, 'longitude' => 51.4], $a)
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->getJson("/api/v1/public/preorder-slots?branch_id={$this->branchB->id}", $a)->assertUnprocessable()->assertJsonValidationErrors('branch_id');

        // Phase 7b stories: B's story is invisible and can't be counted at A; B's product can't be linked from A.
        $this->assertSame([], $this->getJson('/api/v1/public/stories', $a)->assertOk()->json('data'));
        $this->postJson("/api/v1/public/stories/{$this->commerceB['story']}/seen", [], $a)->assertNotFound();
        $this->postJson("/api/v1/public/stories/{$this->commerceB['story']}/click", [], $a)->assertNotFound();
        $this->assertSame(0, $this->inTenant($this->b, fn () => Story::query()->findOrFail($this->commerceB['story'])->views));
        $this->assertSame([], $this->getJson('/api/v1/stories', $this->staffHeaders($this->ownerA, $this->a))->assertOk()->json('data'));
        Storage::fake('public');
        $this->post('/api/v1/stories', [
            'image' => UploadedFile::fake()->image('s.jpg', 900, 1600),
            'link_type' => 'product', 'link_target' => $this->catalogB['product'],
        ], [...$this->staffHeaders($this->ownerA, $this->a), 'Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('link_target');
        $this->app['auth']->forgetGuards();

        // Reorder: B's customer token is worthless at A, and A's customer can't copy B's order.
        $customerB = $this->inTenant($this->b, fn () => Customer::query()->findOrFail($this->commerceB['customer']));
        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $this->commerceB['order']], [...$a, 'X-Cart-Token' => $cart, 'Authorization' => 'Bearer '.$customerB->createToken('t', ['customer'])->plainTextToken])
            ->assertStatus(401);
        $customerA = $this->inTenant($this->a, fn () => Customer::query()->create(['phone_e164' => '+989127777777', 'name' => 'مشتری الف']));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/public/cart/reorder', ['order_id' => $this->commerceB['order']], [...$a, 'X-Cart-Token' => $cart, 'Authorization' => 'Bearer '.$customerA->createToken('t', ['customer'])->plainTextToken])
            ->assertNotFound();
    }

    public function test_kds_me_and_pairing_are_tenant_bound(): void
    {
        // Staff of A cannot open B's KDS "who am I" screen either.
        $this->getJson('/api/v1/kds/me', $this->staffHeaders($this->ownerA, $this->b))->assertForbidden();

        // A fresh, unpaired code minted for B means nothing at A, but still works at B.
        $code = $this->inTenant($this->b, fn () => app(KitchenDevices::class)->create(['branch_id' => $this->branchB->id, 'name' => 'تبلت ب ۲'])['code']);
        $this->postJson('/api/v1/public/kds/pair', ['code' => $code], ['X-Tenant' => $this->a->slug, 'Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'kitchen_pairing_invalid');
        $this->postJson('/api/v1/public/kds/pair', ['code' => $code], ['X-Tenant' => $this->b->slug, 'Accept' => 'application/json'])->assertOk();
    }

    public function test_customer_self_service_and_club_endpoints_reject_cross_tenant_tokens(): void
    {
        $customerB = $this->inTenant($this->b, fn () => Customer::query()->findOrFail($this->commerceB['customer']));
        $tokenB = $customerB->createToken('t', ['customer'])->plainTextToken;
        $a = ['X-Tenant' => $this->a->slug, 'Accept' => 'application/json', 'Authorization' => 'Bearer '.$tokenB];

        foreach ([
            ['GET', '/api/v1/customer/addresses'],
            ['POST', '/api/v1/customer/addresses'],
            ['GET', '/api/v1/customer/orders'],
            ['GET', '/api/v1/customer/profile'],
            ['PATCH', '/api/v1/customer/profile'],
            ['GET', '/api/v1/customer/club'],
            ['GET', '/api/v1/customer/wallet/transactions'],
            ['GET', '/api/v1/customer/points/transactions'],
            ['POST', '/api/v1/customer/points/redeem'],
            ['POST', '/api/v1/customer/referral'],
            ['POST', "/api/v1/customer/orders/{$this->commerceB['order']}/wallet-payment"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, [], $a)->assertStatus(401);
        }

        $this->app['auth']->forgetGuards();
    }

    public function test_global_search_never_crosses_tenants(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);

        foreach (['محصول ب', 'مشتری ب', '۰۹۱۲۷۷۷', '۱'] as $term) {
            $groups = $this->getJson('/api/v1/dashboard/search?q='.urlencode($term), $headers)->assertOk()->json('data');
            $ids = collect($groups)->flatMap(fn ($g) => array_column($g['items'], 'id'))->all();
            $this->assertNotContains($this->catalogB['product'], $ids);
            $this->assertNotContains($this->commerceB['customer'], $ids);
            $this->assertNotContains($this->commerceB['order'], $ids);
        }
    }

    public function test_foreign_ids_inside_payloads_are_rejected(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);

        $this->postJson('/api/v1/catalog/products', [
            'name' => 'نفوذی',
            'category_ids' => [$this->catalogB['category']],
            'variants' => [['base_price' => 1]],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('category_ids.0');

        $this->postJson('/api/v1/catalog/prices/bulk', [
            'target' => ['product_ids' => [$this->catalogB['product']]],
            'operation' => 'exact',
            'value' => 1,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('target.product_ids.0');

        $productA = $this->postJson('/api/v1/catalog/products/quick', ['name' => 'الف', 'price' => 1000], $headers)->assertCreated()->json('data.id');

        $this->putJson("/api/v1/catalog/products/{$productA}/modifier-groups", ['modifier_group_ids' => [$this->catalogB['group']]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('modifier_group_ids.0');
        $this->putJson("/api/v1/catalog/products/{$productA}/availability", ['branch_id' => $this->branchB->id, 'status' => 'hidden'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->putJson("/api/v1/catalog/products/{$productA}/branch-prices", ['branch_id' => $this->branchB->id, 'prices' => [['variant_id' => 'x', 'amount' => 1]]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_foreign_inventory_ids_inside_payloads_are_rejected(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);
        $branchA = $this->inTenant($this->a, fn () => Branch::query()->firstOrFail());

        $this->postJson('/api/v1/inventory/adjustments', ['ingredient_id' => $this->commerceB['ingredient'], 'branch_id' => $branchA->id, 'type' => 'waste', 'quantity' => 1], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('ingredient_id');
        $this->postJson('/api/v1/inventory/purchases', ['supplier_id' => $this->commerceB['supplier'], 'branch_id' => $branchA->id, 'items' => [['ingredient_id' => $this->commerceB['ingredient'], 'quantity' => 1, 'unit_price' => 1]]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'items.0.ingredient_id']);

        $productA = $this->postJson('/api/v1/catalog/products/quick', ['name' => 'آیتم الف', 'price' => 1000], $headers)->assertCreated()->json('data');
        $variantA = $this->getJson("/api/v1/catalog/products/{$productA['id']}", $headers)->json('data.variants.0.id');
        $this->putJson("/api/v1/catalog/products/{$productA['id']}/recipe", ['variants' => [['variant_id' => $variantA, 'items' => [['ingredient_id' => $this->commerceB['ingredient'], 'quantity' => 5]]]]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('variants.0.items.0.ingredient_id');
        $this->assertSame([], $this->getJson('/api/v1/inventory/ingredients', $headers)->assertOk()->json('data'));
    }

    public function test_foreign_operations_ids_inside_payloads_are_rejected(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);
        $branchA = $this->inTenant($this->a, fn () => Branch::query()->firstOrFail());

        $this->postJson('/api/v1/staff/shifts', ['employee_id' => $this->commerceB['employee'], 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHours(4)->toIso8601String()], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson('/api/v1/staff/attendance', ['employee_id' => $this->commerceB['employee'], 'clock_in_at' => now()->subHour()->toIso8601String()], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson('/api/v1/expenses', ['branch_id' => $branchA->id, 'category_id' => $this->commerceB['expense_category'], 'amount' => 1, 'spent_on' => now()->toDateString(), 'method' => 'cash'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        // B's owner is not a member of A: can't be linked as A's employee (and isn't linked at A).
        $this->postJson('/api/v1/staff/employees', ['name' => 'x', 'branch_id' => $branchA->id, 'user_id' => $this->ownerB->id, 'pay_type' => 'hourly', 'rate' => 1], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'user_not_member');
        $this->postJson('/api/v1/time-clock/in', [], $headers)->assertStatus(403)->assertJsonPath('code', 'not_an_employee');
        $this->assertSame([], $this->getJson('/api/v1/staff/attendance', $headers)->assertOk()->json('data'));
    }

    public function test_reports_reject_foreign_branches_and_never_include_other_tenants(): void
    {
        $headers = $this->staffHeaders($this->ownerA, $this->a);
        $branchB = $this->inTenant($this->b, fn () => Branch::query()->firstOrFail());

        foreach (['summary', 'products', 'hours', 'branches', 'customers', 'inventory', 'export'] as $report) {
            $this->getJson("/api/v1/reports/{$report}?branch_id={$branchB->id}", $headers)->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        }
        $names = array_column($this->getJson('/api/v1/reports/branches', $headers)->assertOk()->json('data.branches'), 'name');
        $this->assertNotContains($branchB->name, array_diff($names, $this->inTenant($this->a, fn () => Branch::query()->pluck('name')->all())));
        $this->assertSame(0, $this->getJson('/api/v1/reports/customers', $headers)->json('data.buyers'));
    }

    public function test_foreign_role_ids_cannot_be_assigned(): void
    {
        $roleOfB = $this->inTenant($this->b, fn () => Role::query()->where('key', 'manager')->firstOrFail());

        $this->postJson('/api/v1/team', [
            'name' => 'نفوذی',
            'phone' => '09350000000',
            'role_ids' => [$roleOfB->id],
        ], $this->staffHeaders($this->ownerA, $this->a))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids.0');
    }

    public function test_same_branch_slug_is_allowed_in_different_tenants(): void
    {
        // Both tenants already own a branch with slug "main"; uniqueness is per tenant.
        $this->postJson('/api/v1/branches', ['name' => 'ونک', 'slug' => 'vanak'], $this->staffHeaders($this->ownerA, $this->a))->assertCreated();
        $this->postJson('/api/v1/branches', ['name' => 'ونک', 'slug' => 'vanak'], $this->staffHeaders($this->ownerB, $this->b))->assertCreated();
    }
}
