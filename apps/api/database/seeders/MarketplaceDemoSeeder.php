<?php

namespace Database\Seeders;

use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Core\Actions\CreateTenant;
use App\Modules\Core\Actions\SyncOpeningHours;
use App\Modules\Core\Data\CreateTenantData;
use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Discounts\Models\Discount;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo marketplace: lists «کافه نمونه» and adds a handful of small fictional cafés in several cities
 * (menu, hours, listing), so the explore pages have something real to show. Dev/demo only; idempotent.
 */
class MarketplaceDemoSeeder extends Seeder
{
    private const CAFES = [
        ['narenj', 'کافه نارنج', 'تهران', 35.7008, 51.4105, 'خیابان کریمخان، کوچه‌ی نارنج', ['cafe', 'specialty_coffee'], ['wifi', 'workspace', 'no_smoking'], 2, 'اسپرسوی تازه‌برشته و میزهای کار آرام', [['اسپرسو دوبل', 95_000], ['کاپوچینو', 140_000], ['چیزکیک نیویورکی', 180_000]], ['08:00', '22:00']],
        ['koohpayeh', 'قهوه‌ی کوهپایه', 'تهران', 35.8032, 51.4521, 'دربند، نرسیده به میدان', ['cafe_restaurant', 'breakfast'], ['outdoor', 'family', 'parking'], 3, 'صبحانه‌ی کوهستانی با منظره‌ی دربند', [['املت محلی', 210_000], ['نیمرو و نان تازه', 160_000], ['چای آتیشی', 70_000]], ['07:00', '23:30']],
        ['eram', 'کافه باغ ارم', 'شیراز', 29.6363, 52.5233, 'خیابان ارم، روبه‌روی باغ', ['cafe', 'dessert'], ['outdoor', 'family', 'live_music'], 2, 'فالوده‌ی شیرازی و قهوه زیر نارنج‌ها', [['فالوده شیرازی', 90_000], ['لاته', 130_000], ['کیک هویج', 150_000]], ['09:00', '23:00']],
        ['naghsh', 'چای‌خانه‌ی نقش', 'اصفهان', 32.6574, 51.6779, 'میدان نقش جهان، بازارچه‌ی هنر', ['tea_house'], ['family', 'no_smoking'], 1, 'چای دارچین و گز تازه در دل میدان', [['چای دارچین', 60_000], ['قلیان‌خانه‌ی بدون دود: دمنوش', 80_000], ['گز و نبات', 50_000]], ['10:00', '22:00']],
        ['yas', 'بستنی سنتی یاس', 'اصفهان', 32.6421, 51.6655, 'چهارباغ عباسی', ['ice_cream'], ['family'], 1, 'بستنی زعفرانی دست‌ساز از ۱۳۵۲', [['بستنی سنتی', 85_000], ['آب‌طالبی', 70_000], ['فالوده بستنی', 110_000]], ['11:00', '01:00']],
        ['toranj', 'نانوایی و شیرینی ترنج', 'مشهد', 36.2972, 59.6067, 'بلوار سجاد، نبش سجاد ۱۲', ['bakery', 'dessert'], ['parking'], 2, 'نان فرانسوی و شیرینی خامه‌ای هر صبح', [['کروسان کره‌ای', 75_000], ['نان باگت', 45_000], ['شیرینی ناپلئونی', 120_000]], ['06:30', '21:00']],
        ['sabz', 'سالاد و اسموتی سبز', 'تبریز', 38.0773, 46.2893, 'خیابان ولیعصر تبریز', ['healthy', 'cafe'], ['wifi', 'pet_friendly', 'wheelchair'], 3, 'کاسه‌های سالم، اسموتی و قهوه‌ی بدون شکر', [['بول کینوا', 260_000], ['اسموتی سبز', 150_000], ['آمریکانو', 90_000]], ['09:00', '21:30']],
    ];

    public function run(CreateTenant $createTenant, SyncOpeningHours $hours, SaveProduct $save, TenantContext $context): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('MarketplaceDemoSeeder must not run in production.');
        }

        // The main demo café joins the marketplace.
        $main = Tenant::query()->where('slug', 'cafe-nemooneh')->first();
        if ($main !== null) {
            $context->runAs($main, function (): void {
                MarketplaceListing::query()->firstOrCreate([], [
                    'is_listed' => true, 'headline' => 'قهوه‌ی تخصصی، کیک خانگی و سفارش آنلاین', 'about' => 'کافه‌ای دنج در خیابان ولیعصر با قهوه‌ی تازه‌برشته، صبحانه و شیرینی روز.',
                    'categories' => ['cafe', 'specialty_coffee', 'breakfast'], 'amenities' => ['wifi', 'workspace', 'outdoor', 'no_smoking'], 'price_level' => 2, 'listed_at' => now()->subDays(20),
                ]);
                app(ProjectStore::class)->handle();
            });
        }

        $this->createCafes($createTenant, $hours, $save, $context);
        $this->enrich($context);
    }

    /** Areas and a few automatic offers, applied to existing demo cafés too (idempotent). */
    private function enrich(TenantContext $context): void
    {
        $areas = [
            'cafe-nemooneh' => ['main' => 'یوسف‌آباد', 'vanak' => 'ونک'], 'narenj' => ['main' => 'کریمخان'], 'koohpayeh' => ['main' => 'دربند'],
            'eram' => ['main' => 'ارم'], 'naghsh' => ['main' => 'نقش جهان'], 'yas' => ['main' => 'چهارباغ'], 'toranj' => ['main' => 'سجاد'], 'sabz' => ['main' => 'ولیعصر'],
        ];
        $offers = [
            'narenj' => ['قهوه‌ی صبح', 1500, 0], 'eram' => ['فالوده‌ی تابستان', 1000, 500_000], 'toranj' => ['شیرینی تازه', 2000, 1_000_000],
        ];
        foreach (Tenant::query()->whereIn('slug', array_keys($areas))->get() as $tenant) {
            $context->runAs($tenant, function () use ($tenant, $areas, $offers): void {
                foreach ($areas[$tenant->slug] as $branchSlug => $district) {
                    Branch::query()->where('slug', $branchSlug)->update(['district' => $district]);
                }
                if (isset($offers[$tenant->slug]) && ! Discount::query()->exists()) {
                    [$name, $basisPoints, $minOrderToman] = $offers[$tenant->slug];
                    Discount::query()->create(['name' => $name, 'kind' => 'percent', 'value' => $basisPoints, 'applies_to' => 'order', 'min_order' => $minOrderToman * 10, 'is_active' => true, 'priority' => 1]);
                }
                app(ProjectStore::class)->handle();
            });
        }
    }

    private function createCafes(CreateTenant $createTenant, SyncOpeningHours $hours, SaveProduct $save, TenantContext $context): void
    {
        foreach (self::CAFES as $i => [$slug, $name, $city, $lat, $lng, $address, $categories, $amenities, $price, $headline, $items, [$opens, $closes]]) {
            if (Tenant::query()->where('slug', $slug)->exists()) {
                continue;
            }
            $tenant = $createTenant->handle(new CreateTenantData(
                name: $name, slug: $slug, ownerName: "مالک {$name}", ownerEmail: null,
                ownerPhoneE164: '+98912900'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), ownerPassword: 'password',
                firstBranchName: 'شعبه اصلی', subdomainBase: config('tenancy.subdomain_base'),
            ));
            $tenant->update(['status' => TenantStatus::Active]);

            $context->runAs($tenant, function () use ($city, $lat, $lng, $address, $categories, $amenities, $price, $headline, $items, $opens, $closes, $hours, $save, $i): void {
                $branch = Branch::query()->firstOrFail();
                $branch->update(['city' => $city, 'province' => $city, 'address' => $address, 'latitude' => $lat, 'longitude' => $lng, 'phone' => '0'.(21 + $i).'3344556'.$i]);
                $hours->handle($branch, array_map(fn (int $d) => ['weekday' => $d, 'opens_at' => $opens, 'closes_at' => $closes], [1, 2, 3, 4, 5, 6, 7]));
                foreach ($items as [$item, $toman]) {
                    $save->handle(new ProductData($item), null, [new VariantData(null, null, $toman * 10)]);
                }
                MarketplaceListing::query()->create([
                    'is_listed' => true, 'headline' => $headline, 'categories' => $categories, 'amenities' => $amenities,
                    'price_level' => $price, 'listed_at' => now()->subDays(3 * $i),
                ]);
                app(ProjectStore::class)->handle();
            });
        }
    }
}
