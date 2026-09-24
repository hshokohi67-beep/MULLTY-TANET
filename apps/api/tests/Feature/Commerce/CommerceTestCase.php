<?php

namespace Tests\Feature\Commerce;

use App\Modules\Catalog\Actions\SaveModifierGroup;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Catalog\Models\ModifierGroup;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Actions\Tables\ManageTableQr;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A cafe at Vanak square (35.7575, 51.4099) with: espresso (650,000 rial), latte (small/large)
 * with a required «نوع شیر» (milk type) group (almond milk +250,000 rial), one table with a QR code,
 * and a 3 km delivery zone (fee 300,000; free above 3,000,000; minimum 500,000).
 */
abstract class CommerceTestCase extends TestCase
{
    protected Tenant $tenant;

    protected User $owner;

    protected Branch $branch;

    protected Product $espresso;

    protected Product $latte;

    protected ModifierGroup $milk;

    protected RestaurantTable $table;

    protected string $qrToken;

    protected function setUp(): void
    {
        parent::setUp();

        ['tenant' => $this->tenant, 'owner' => $this->owner] = $this->createTenantWithOwner('cafe-a');

        $this->inTenant($this->tenant, function (): void {
            $this->branch = Branch::query()->where('slug', 'main')->firstOrFail();
            $this->branch->update(['latitude' => 35.7575, 'longitude' => 51.4099]);

            $this->milk = app(SaveModifierGroup::class)->handle(['name' => 'نوع شیر', 'min_select' => 1, 'max_select' => 1], [
                ['name' => 'شیر معمولی', 'price_delta' => 0, 'is_default' => true],
                ['name' => 'شیر بادام', 'price_delta' => 250_000],
            ]);

            $save = app(SaveProduct::class);
            $this->espresso = $save->handle(new ProductData('اسپرسو'), null, [new VariantData(null, null, 650_000)]);
            $this->latte = $save->handle(new ProductData('لاته'), null, [new VariantData(null, 'کوچک', 850_000), new VariantData(null, 'بزرگ', 1_050_000)]);
            $this->latte->modifierGroups()->attach($this->milk->id, ['tenant_id' => $this->tenant->id, 'sort' => 0]);

            $this->table = RestaurantTable::query()->create(['branch_id' => $this->branch->id, 'label' => 'میز ۱']);
            $this->qrToken = app(ManageTableQr::class)->issue($this->table)['token'];

            DeliveryZone::query()->create([
                'branch_id' => $this->branch->id, 'name' => 'تا ۳ کیلومتر', 'type' => 'radius', 'radius_m' => 3000,
                'delivery_fee' => 300_000, 'free_delivery_min' => 3_000_000, 'min_order' => 500_000, 'eta_minutes' => 40,
            ]);
        });
    }

    protected function variant(Product $product, int $index = 0): string
    {
        return $this->inTenant($this->tenant, fn () => $product->variants()->orderBy('sort')->get()[$index]->id);
    }

    protected function milkOption(string $name): string
    {
        return $this->inTenant($this->tenant, fn () => $this->milk->modifiers()->where('name', $name)->value('id'));
    }

    /** @return array<string, string> */
    protected function publicHeaders(array $extra = []): array
    {
        return ['X-Tenant' => $this->tenant->slug, 'Accept' => 'application/json', ...$extra];
    }

    /** @return array{0: Customer, 1: string} */
    protected function customer(string $phone = '+989121111111'): array
    {
        $customer = $this->inTenant($this->tenant, fn () => Customer::query()->firstOrCreate(['phone_e164' => $phone], ['name' => 'مشتری نمونه']));

        return [$customer, $customer->createToken('t', ['customer'])->plainTextToken];
    }

    protected function joinTable(): string
    {
        return $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], $this->publicHeaders())
            ->assertOk()->json('data.session_token');
    }

    /** Creates a cart and returns its token. */
    protected function cart(string $type, array $headers = []): string
    {
        $body = ['order_type' => $type];
        if ($type !== 'qr_table') {
            $body['branch_id'] = $this->branch->id;
        }

        return $this->postJson('/api/v1/public/carts', $body, $this->publicHeaders($headers))->assertCreated()->json('data.cart_token');
    }

    protected function addItem(string $cartToken, string $variantId, int $quantity = 1, array $modifiers = [], array $headers = []): TestResponse
    {
        return $this->postJson('/api/v1/public/cart/items', ['variant_id' => $variantId, 'quantity' => $quantity, 'modifier_ids' => $modifiers], $this->publicHeaders(['X-Cart-Token' => $cartToken, ...$headers]));
    }

    protected function checkout(string $cartToken, array $body = [], array $headers = [], ?string $key = null): TestResponse
    {
        return $this->postJson('/api/v1/public/checkout', $body, $this->publicHeaders([
            'X-Cart-Token' => $cartToken,
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
            ...$headers,
        ]));
    }

    /** Places a guest QR order for one espresso and returns the response. */
    protected function quickQrOrder(): TestResponse
    {
        $session = $this->joinTable();
        $cart = $this->cart('qr_table', ['X-Table-Session' => $session]);
        $this->addItem($cart, $this->variant($this->espresso))->assertCreated();

        return $this->checkout($cart)->assertCreated();
    }
}
