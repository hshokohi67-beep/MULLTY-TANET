<?php

namespace App\Modules\Marketplace\Actions;

use App\Modules\Analytics\Models\DailyMetric;
use App\Modules\Billing\Support\Entitlements;
use App\Modules\Catalog\Actions\BuildPublicMenu;
use App\Modules\Catalog\Models\Product;
use App\Modules\Commerce\Contracts\OnlinePaymentGate;
use App\Modules\Commerce\Models\DeliveryZone;
use App\Modules\Commerce\Models\RestaurantTable;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\BranchOpeningHour;
use App\Modules\Core\Models\TenantBranding;
use App\Modules\Core\Support\TenantSettings;
use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceStore;
use App\Modules\Marketplace\Support\MarketplaceCatalog;
use App\Modules\Marketplace\Support\SearchText;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the current café's public marketplace rows (one per eligible branch) from an explicit
 * allow-list of public facts, replacing whatever was there. Ineligible cafés are removed. Returns
 * the eligibility checklist the owner sees.
 *
 * @phpstan-type Check array{key: string, label: string, ok: bool, required: bool}
 */
final class ProjectStore
{
    /** @return array{eligible: bool, checks: list<array{key: string, label: string, ok: bool, required: bool}>} */
    public function handle(): array
    {
        $tenant = app(TenantContext::class)->require();
        $listing = MarketplaceListing::query()->first();
        $branding = TenantBranding::query()->first();
        $branches = Branch::query()->where('is_active', true)->with('openingHours')->orderBy('sort')->orderBy('created_at')->get()
            ->filter(fn (Branch $b) => trim((string) $b->getAttribute('city')) !== '')->values();
        $productCount = Product::query()->where('is_active', true)->count();
        $state = app(Entitlements::class)->snapshot()['state'];

        $checks = [
            ['key' => 'listed', 'label' => 'نمایش در بازارگاه روشن باشد', 'ok' => (bool) $listing?->is_listed, 'required' => true],
            ['key' => 'moderation', 'label' => $listing?->hidden_at ? 'پلتفرم نمایش را متوقف کرده: '.($listing->hidden_reason ?? '') : 'مورد تأیید پلتفرم', 'ok' => $listing?->hidden_at === null, 'required' => true],
            ['key' => 'subscription', 'label' => 'اشتراک فعال باشد', 'ok' => $tenant->canOperate() && $state->writable(), 'required' => true],
            ['key' => 'city', 'label' => 'شهر حداقل یک شعبه‌ی فعال ثبت شده باشد', 'ok' => $branches->isNotEmpty(), 'required' => true],
            ['key' => 'menu', 'label' => 'منو حداقل یک محصول فعال داشته باشد', 'ok' => $productCount > 0, 'required' => true],
            ['key' => 'logo', 'label' => 'لوگو (برای دیده‌شدن بهتر)', 'ok' => $branding?->logo_path !== null, 'required' => false],
            ['key' => 'cover', 'label' => 'عکس کاور (برای دیده‌شدن بهتر)', 'ok' => $branding?->cover_path !== null, 'required' => false],
            ['key' => 'headline', 'label' => 'یک جمله‌ی معرفی کوتاه', 'ok' => filled($listing?->headline), 'required' => false],
        ];
        $eligible = collect($checks)->where('required', true)->every(fn (array $c) => $c['ok']);

        $rows = $eligible && $listing !== null ? $this->rows($listing, $branding, $branches) : [];

        DB::transaction(function () use ($tenant, $rows): void {
            MarketplaceStore::query()->where('tenant_id', $tenant->id)->delete();
            foreach ($rows as $row) {
                MarketplaceStore::query()->create($row);
            }
        });

        return ['eligible' => $eligible, 'checks' => $checks];
    }

    /**
     * @param  Collection<int, Branch>  $branches
     * @return list<array<string, mixed>>
     */
    private function rows(MarketplaceListing $listing, ?TenantBranding $branding, Collection $branches): array
    {
        $tenant = app(TenantContext::class)->require();
        $disk = Storage::disk(config('filesystems.media_disk'));
        $snapshot = app(Entitlements::class)->snapshot();
        // Featured: a manual platform feature, or the add-on for as long as the subscription runs.
        $featured = $listing->featured_until;
        $paid = $snapshot['features']['marketplace_featured'] === true ? $snapshot['subscription']->endsAt() : null;
        if ($paid !== null && ($featured === null || $paid->gt($featured))) {
            $featured = $paid;
        }

        $highlights = $this->highlights($branches->first());
        $categories = array_values(array_intersect($listing->categories, array_keys(MarketplaceCatalog::CATEGORIES)));
        $amenities = array_values(array_intersect($listing->amenities, array_keys(MarketplaceCatalog::AMENITIES)));
        $online = app(OnlinePaymentGate::class)->onlineAvailable();
        $preorder = (bool) TenantSettings::get('orders.allow_preorder_when_closed');
        $delivery = DeliveryZone::query()->where('is_active', true)->distinct()->pluck('branch_id')->flip();
        $tables = RestaurantTable::query()->where('is_active', true)->distinct()->pluck('branch_id')->flip();
        $orders = DailyMetric::query()->where('business_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('branch_id, SUM(orders) as n')->groupBy('branch_id')->pluck('n', 'branch_id');

        return $branches->map(function (Branch $b) use ($tenant, $listing, $branding, $disk, $featured, $highlights, $categories, $amenities, $online, $preorder, $delivery, $tables, $orders, $branches): array {
            $words = [
                $tenant->name, $b->name, $listing->headline, $b->getAttribute('city'), $b->getAttribute('province'),
                ...array_map(fn (string $k) => MarketplaceCatalog::CATEGORIES[$k], $categories),
                ...array_map(fn (string $k) => MarketplaceCatalog::AMENITIES[$k], $amenities),
                ...array_column($highlights, 'name'),
            ];

            return [
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenant->id,
                'store_slug' => $tenant->slug,
                'branch_slug' => $b->slug,
                'name' => $tenant->name,
                'branch_name' => $b->name,
                'branch_count' => $branches->count(),
                'headline' => $listing->headline,
                'about' => $listing->about,
                'city' => trim((string) $b->getAttribute('city')),
                'province' => $b->getAttribute('province'),
                'address' => $b->getAttribute('address'),
                'phone' => $b->getAttribute('phone'),
                'latitude' => $b->getAttribute('latitude'),
                'longitude' => $b->getAttribute('longitude'),
                'categories' => $categories,
                'category_keys' => '|'.implode('|', $categories).'|',
                'amenities' => $amenities,
                'amenity_keys' => '|'.implode('|', $amenities).'|',
                'price_level' => $listing->price_level,
                'logo_url' => $branding?->logo_path ? $disk->url($branding->logo_path) : null,
                'cover_url' => $branding?->cover_path ? $disk->url($branding->cover_path) : null,
                'primary_color' => $branding?->primary_color,
                'hours' => $b->openingHours->map(fn (BranchOpeningHour $h) => ['weekday' => $h->weekday, 'opens_at' => substr($h->opens_at, 0, 5), 'closes_at' => substr($h->closes_at, 0, 5)])->values()->all(),
                'timezone' => $tenant->timezone,
                'services' => [
                    'dine_in' => $tables->has($b->id),
                    'takeaway' => true,
                    'delivery' => $delivery->has($b->id) && $b->getAttribute('latitude') !== null,
                    'online_payment' => $online,
                    'preorder' => $preorder,
                ],
                'highlights' => $highlights,
                'search_text' => SearchText::normalize(implode(' ', array_filter($words, fn ($w) => is_string($w) && $w !== ''))),
                'popularity' => (int) floor(log(((int) ($orders[$b->id] ?? 0)) + 1, 2) * 10),
                'featured_until' => $featured,
                'listed_at' => $listing->listed_at,
            ];
        })->values()->all();
    }

    /** @return list<array{name: string, price_from: ?int, image_url: ?string}> up to 6 public menu items, photos and featured first */
    private function highlights(?Branch $branch): array
    {
        if ($branch === null) {
            return [];
        }
        $menu = app(BuildPublicMenu::class)->handle($branch);
        $picked = [];
        foreach (is_array($menu['products'] ?? null) ? $menu['products'] : [] as $p) {
            if (! is_array($p) || ! ($p['is_available'] ?? false) || ! is_int($p['price_from'] ?? null)) {
                continue;
            }
            $image = is_array($p['images'] ?? null) && isset($p['images'][0]['url']) ? (string) $p['images'][0]['url'] : null;
            $picked[] = ['rank' => ($image === null ? 2 : 0) + (($p['is_featured'] ?? false) ? 0 : 1), 'name' => (string) ($p['name'] ?? ''), 'price_from' => $p['price_from'], 'image_url' => $image];
        }
        usort($picked, fn (array $a, array $b) => $a['rank'] <=> $b['rank']);

        return array_map(fn (array $h) => ['name' => $h['name'], 'price_from' => $h['price_from'], 'image_url' => $h['image_url']], array_slice($picked, 0, 6));
    }
}
