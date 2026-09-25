<?php

namespace Tests\Feature\Advertising;

use App\Modules\Advertising\Models\AdCampaign;
use App\Modules\Advertising\Models\AdPlacement;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Actions\SaveProduct;
use App\Modules\Catalog\Data\ProductData;
use App\Modules\Catalog\Data\VariantData;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Modules\Marketplace\Actions\ProjectStore;
use App\Modules\Marketplace\Models\MarketplaceListing;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Payments\PaymentsTestCase;

/**
 * cafe-a (Tehran) advertises in «خوراک‌گردی». Saturday 2026-09-26, 10:00 Tehran; campaigns start
 * on Sunday the 27th by default.
 */
final class AdvertisingTest extends PaymentsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00', 'Asia/Tehran'));
        $this->inTenant($this->tenant, fn () => $this->branch->update(['city' => 'تهران', 'province' => 'تهران', 'address' => 'خیابان ولیعصر']));
    }

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    /** @return array<string, string> */
    private function admin(): array
    {
        return $this->staffHeaders(User::factory()->platformAdmin()->create());
    }

    /** @return array<string, string> */
    private function pub(): array
    {
        return ['Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 test'];
    }

    private function listCafe(): void
    {
        $this->putJson('/api/v1/marketplace/listing', [
            'is_listed' => true, 'headline' => 'قهوه‌ی دمی و کیک خانگی', 'categories' => ['cafe', 'specialty_coffee'], 'amenities' => ['wifi'], 'price_level' => 2,
        ], $this->h())->assertOk()->assertJsonPath('data.eligible', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function campaign(array $overrides = []): array
    {
        return $this->postJson('/api/v1/ads/campaigns', [
            'name' => 'کمپین پاییز', 'placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 3, 'cities' => [],
            'headline' => 'قهوه‌ی تازه‌ی پاییزی', 'body' => 'با کیک خانگی', 'cta' => 'menu', ...$overrides,
        ], $this->h())->assertCreated()->json('data');
    }

    private function submitAndApprove(string $id): void
    {
        $this->postJson("/api/v1/ads/campaigns/{$id}/submit", [], $this->h())->assertOk()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/v1/platform/ads/{$id}/approve", [], $this->admin())->assertOk()->assertJsonPath('data.status', 'approved');
    }

    /** @return array{invoice: string, authority: string} */
    private function startPayment(string $id): array
    {
        $url = (string) $this->postJson("/api/v1/ads/campaigns/{$id}/pay", [], $this->h())->assertOk()->json('data.redirect_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return ['invoice' => (string) $q['invoice'], 'authority' => (string) $q['Authority']];
    }

    /** @param  array{invoice: string, authority: string}  $payment */
    private function verifyAd(array $payment): TestResponse
    {
        return $this->postJson("/api/v1/ads/invoices/{$payment['invoice']}/verify", ['authority' => $payment['authority']], $this->h());
    }

    /**
     * Another listed café with a paid campaign already running (set up directly).
     *
     * @param  list<string>  $categories
     * @param  list<string>  $cities
     */
    private function rival(string $slug, string $city, array $categories, ?string $placement = null, array $cities = []): Tenant
    {
        ['tenant' => $t] = $this->createTenantWithOwner($slug);
        $this->inTenant($t, function () use ($city, $categories, $placement, $cities): void {
            Branch::query()->firstOrFail()->update(['city' => $city]);
            app(SaveProduct::class)->handle(new ProductData('نان سنگک'), null, [new VariantData(null, null, 200_000)]);
            MarketplaceListing::query()->create(['is_listed' => true, 'categories' => $categories, 'amenities' => [], 'price_level' => 1, 'listed_at' => now()]);
            app(ProjectStore::class)->handle();
            if ($placement !== null) {
                $this->paidCampaign($placement, $cities);
            }
        });

        return $t;
    }

    /** @param  list<string>  $cities */
    private function paidCampaign(string $placement, array $cities = [], ?CarbonImmutable $from = null): AdCampaign
    {
        $from ??= CarbonImmutable::now()->subHour();

        return AdCampaign::query()->forceCreate([
            'ref' => Str::random(16), 'name' => 'رقیب', 'placement' => $placement, 'status' => AdCampaign::PAID, 'start_date' => $from->toDateString(), 'days' => 3,
            'starts_at' => $from, 'ends_at' => $from->addDays(3), 'cities' => $cities, 'headline' => 'تبلیغ رقیب', 'cta' => 'visit',
            'image_path' => $placement === 'home_banner' ? 'x/wide.webp' : null, 'daily_price' => 1, 'amount' => 3, 'paid_at' => now(),
        ]);
    }

    /** Fails on anything internal in a public payload. */
    private function assertNothingPrivate(string $json, string ...$ids): void
    {
        foreach ([$this->tenant->id, '"tenant_id"', '"campaign_id"', '"id"', 'review_note', 'amount', ...$ids] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "public response leaked {$needle}");
        }
    }

    public function test_banner_campaign_from_draft_to_paid_runs_only_inside_its_dates(): void
    {
        $this->listCafe();
        $c = $this->campaign(['placement' => 'home_banner', 'cta' => 'offer']);
        $this->assertSame(['draft', 3 * 1_800_000], [$c['status'], $c['amount']]);

        // A banner needs a picture, and a wide one.
        $this->postJson("/api/v1/ads/campaigns/{$c['id']}/submit", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ad_image_required');
        $this->post("/api/v1/ads/campaigns/{$c['id']}/image", ['image' => UploadedFile::fake()->image('small.jpg', 400, 300)], $this->h())->assertStatus(422);
        $image = $this->post("/api/v1/ads/campaigns/{$c['id']}/image", ['image' => UploadedFile::fake()->image('b.jpg', 1600, 600)], $this->h())->assertOk()->json('data.image_url');
        $this->assertStringEndsWith('.webp', (string) $image);

        $this->submitAndApprove($c['id']);
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);

        $payment = $this->startPayment($c['id']);
        $this->verifyAd($payment)->assertOk()->assertJsonPath('data.paid', true);
        $this->verifyAd($payment)->assertOk()->assertJsonPath('data.paid', true); // idempotent
        $this->getJson("/api/v1/ads/campaigns/{$c['id']}", $this->h())->assertJsonPath('data.campaign.status', 'paid')->assertJsonPath('data.campaign.phase', 'scheduled');
        $invoice = collect($this->getJson('/api/v1/billing/invoices', $this->h())->json('data'))->firstWhere('kind', 'ad');
        $this->assertSame([null, 'paid', 5_940_000], [$invoice['plan'], $invoice['status'], $invoice['total']]);

        // Nothing before the first day.
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);

        $this->travelTo(CarbonImmutable::parse('2026-09-27 09:00', 'Asia/Tehran'));
        $res = $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertOk();
        $banner = $res->json('data.banners.0');
        $this->assertSame(['cafe-a', 'قهوه‌ی تازه‌ی پاییزی', 'دیدن تخفیف', '/explore/cafe-a'], [$banner['store'], $banner['headline'], $banner['cta_label'], $banner['href']]);
        $this->assertNotEmpty($banner['token']);
        $this->assertNothingPrivate((string) $res->getContent(), $c['id']);

        // The platform can pause it at once.
        $this->postJson("/api/v1/platform/ads/{$c['id']}/suspend", ['reason' => 'تصویر گمراه‌کننده'], $this->admin())->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);
        $this->postJson("/api/v1/platform/ads/{$c['id']}/resume", [], $this->admin())->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertCount(1, $this->getJson('/api/v1/public/marketplace/home', $this->pub())->json('data.banners'));

        // A café that leaves the marketplace takes its ads with it.
        $this->putJson('/api/v1/marketplace/listing', ['is_listed' => false, 'categories' => ['cafe'], 'amenities' => []], $this->h())->assertOk();
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);
        $this->listCafe();

        // Three days: 27th, 28th, 29th.
        $this->travelTo(CarbonImmutable::parse('2026-09-29 23:30', 'Asia/Tehran'));
        $this->assertCount(1, $this->getJson('/api/v1/public/marketplace/home', $this->pub())->json('data.banners'));
        $this->travelTo(CarbonImmutable::parse('2026-09-30 00:30', 'Asia/Tehran'));
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);
        $this->getJson("/api/v1/ads/campaigns/{$c['id']}", $this->h())->assertJsonPath('data.campaign.phase', 'ended');
    }

    public function test_editing_review_rejection_and_cancellation_rules(): void
    {
        $this->listCafe();

        // Only the café's own cities; never a past day.
        $this->postJson('/api/v1/ads/campaigns', ['name' => 'x', 'placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 2, 'cities' => ['شیراز'], 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'ad_unknown_city');
        $this->postJson('/api/v1/ads/campaigns', ['name' => 'x', 'placement' => 'search_top', 'start_date' => '2026-09-25', 'days' => 2, 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'ad_start_passed');
        $this->postJson('/api/v1/ads/campaigns', ['name' => 'x', 'placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 31, 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422);

        $c = $this->campaign(['cities' => ['تهران']]);
        $id = $c['id'];
        $this->postJson("/api/v1/ads/campaigns/{$id}/submit", [], $this->h())->assertOk();
        $this->postJson("/api/v1/platform/ads/{$id}/reject", ['reason' => 'متن نامشخص است'], $this->admin())->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->getJson("/api/v1/ads/campaigns/{$id}", $this->h())->assertJsonPath('data.campaign.review_note', 'متن نامشخص است');

        // Edit, resend, approve.
        $this->putJson("/api/v1/ads/campaigns/{$id}", ['name' => 'کمپین پاییز', 'placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 3, 'headline' => 'قهوه‌ی دمی تازه', 'cta' => 'menu'], $this->h())
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->submitAndApprove($id);

        // Changing an approved campaign sends it back to review, re-priced.
        $this->putJson("/api/v1/ads/campaigns/{$id}", ['name' => 'کمپین پاییز', 'placement' => 'search_top', 'start_date' => '2026-09-28', 'days' => 5, 'headline' => 'قهوه‌ی دمی تازه', 'cta' => 'menu'], $this->h())
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount', 5 * 900_000);
        $this->postJson("/api/v1/ads/campaigns/{$id}/pay", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ad_wrong_state');
        $this->postJson("/api/v1/platform/ads/{$id}/approve", [], $this->admin())->assertOk();

        // Paid: frozen.
        $this->verifyAd($this->startPayment($id))->assertJsonPath('data.paid', true);
        $this->putJson("/api/v1/ads/campaigns/{$id}", ['name' => 'x', 'placement' => 'search_top', 'start_date' => '2026-09-28', 'days' => 1, 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'ad_not_editable');
        $this->postJson("/api/v1/ads/campaigns/{$id}/cancel", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ad_not_editable');

        // An approved (unpaid) one can still be cancelled; its open invoice is voided.
        $other = $this->campaign(['name' => 'کمپین دوم']);
        $this->submitAndApprove($other['id']);
        $this->startPayment($other['id']);
        $this->travel(21)->minutes(); // the abandoned gateway session no longer blocks
        $this->postJson("/api/v1/ads/campaigns/{$other['id']}/cancel", [], $this->h())->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->inTenant($this->tenant, fn () => $this->assertSame('void', BillingInvoice::query()->where('subject_id', $other['id'])->value('status')));

        // Drafts stay private to the café.
        $draft = $this->campaign(['name' => 'پیش‌نویس']);
        $this->assertNotContains($draft['id'], collect($this->getJson('/api/v1/platform/ads', $this->admin())->json('data.campaigns'))->pluck('id')->all());
    }

    public function test_submitting_needs_a_listed_cafe_and_room_in_the_placement(): void
    {
        $c = $this->campaign();
        $this->postJson("/api/v1/ads/campaigns/{$c['id']}/submit", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ad_store_not_listed');
        $this->listCafe();

        AdPlacement::query()->where('key', 'search_top')->update(['capacity' => 1]);
        $this->rival('cafe-r', 'تهران', ['cafe'], 'search_top');
        $this->postJson('/api/v1/ads/quote', ['placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 3], $this->h())
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.remaining', 0)->assertJsonPath('data.total', 2_970_000);
        $this->postJson("/api/v1/ads/campaigns/{$c['id']}/submit", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'ad_placement_full');

        // After the rival's dates there is room.
        $this->putJson("/api/v1/ads/campaigns/{$c['id']}", ['name' => 'کمپین', 'placement' => 'search_top', 'start_date' => '2026-10-01', 'days' => 2, 'headline' => 'x', 'cta' => 'menu'], $this->h())->assertOk();
        $this->postJson("/api/v1/ads/campaigns/{$c['id']}/submit", [], $this->h())->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_sponsored_results_are_relevant_labelled_targeted_and_never_duplicated(): void
    {
        $this->listCafe();
        $this->rival('cafe-b', 'تهران', ['bakery']);
        $this->rival('cafe-s', 'شیراز', ['cafe'], 'search_top', ['شیراز']);
        $this->inTenant($this->tenant, fn () => $this->paidCampaign('search_top', ['تهران']));

        // Tehran cafés: cafe-a is sponsored (top, labelled) and not repeated below.
        $res = $this->getJson('/api/v1/public/marketplace/stores?city='.urlencode('تهران'), $this->pub())->assertOk();
        $this->assertSame(['cafe-a'], array_column($res->json('sponsored'), 'store'));
        $this->assertSame('تبلیغ رقیب', $res->json('sponsored.0.ad.headline'));
        $this->assertSame('/explore/cafe-a', $res->json('sponsored.0.ad.href'));
        $this->assertSame(['cafe-b'], array_column($res->json('data'), 'store'));
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertNothingPrivate((string) $res->getContent());

        // Only when it matches the search: a bakery search doesn't show a café's ad.
        $this->getJson('/api/v1/public/marketplace/stores?category=bakery&city='.urlencode('تهران'), $this->pub())->assertJsonPath('sponsored', []);

        // Targeting: the Shiraz ad only for Shiraz; without a city, targeted ads stay out.
        $this->assertSame(['cafe-s'], array_column($this->getJson('/api/v1/public/marketplace/stores?city='.urlencode('شیراز'), $this->pub())->json('sponsored'), 'store'));
        $this->getJson('/api/v1/public/marketplace/stores?category=cafe', $this->pub())->assertJsonPath('sponsored', []);

        // Never on page 2 or in favourites.
        $this->getJson('/api/v1/public/marketplace/stores?page=2&city='.urlencode('تهران'), $this->pub())->assertJsonPath('sponsored', []);
        $this->getJson('/api/v1/public/marketplace/stores?stores[]=cafe-a&stores[]=cafe-b', $this->pub())->assertJsonPath('sponsored', [])->assertJsonCount(2, 'data');

        // City banners above the results of that city only.
        $this->rival('cafe-h', 'تهران', ['dessert'], 'home_banner', ['تهران']);
        $this->assertSame(['cafe-h'], array_column($this->getJson('/api/v1/public/marketplace/stores?city='.urlencode('تهران'), $this->pub())->json('banners'), 'store'));
        $this->getJson('/api/v1/public/marketplace/stores?city='.urlencode('شیراز'), $this->pub())->assertJsonPath('banners', []);
        $this->getJson('/api/v1/public/marketplace/home', $this->pub())->assertJsonPath('data.banners', []);
    }

    public function test_events_need_a_signed_live_token_and_count_once_per_visitor(): void
    {
        $this->listCafe();
        $c = $this->inTenant($this->tenant, fn () => $this->paidCampaign('home_banner'));
        $token = (string) $this->getJson('/api/v1/public/marketplace/home', $this->pub())->json('data.banners.0.token');

        $send = fn (string $type, string $ip = '10.0.0.1', ?string $t = null) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/public/ads/events', ['token' => $t ?? $token, 'type' => $type], $this->pub())->assertNoContent();
        $send('impression');
        $send('impression');
        $send('click');
        $send('click');
        $send('impression', '10.0.0.2');
        $send('impression', '10.0.0.1', substr($token, 0, -4).'0000');  // forged
        $send('impression', '10.0.0.3', 'nope');
        $this->postJson('/api/v1/public/ads/events', ['token' => $token, 'type' => 'purchase'], $this->pub())->assertStatus(422);

        $this->travel(61)->minutes();
        $send('impression'); // a new hour counts again
        $this->travel(2)->hours();
        $send('impression', '10.0.0.9'); // the token has expired

        $stats = $this->getJson("/api/v1/ads/campaigns/{$c->id}", $this->h())->assertOk()->json('data.campaign');
        $this->assertSame([3, 1, 33.3], [$stats['impressions'], $stats['clicks'], $stats['ctr']]);
        $this->getJson('/api/v1/ads', $this->h())->assertOk()->assertJsonPath('data.summary.impressions', 3)->assertJsonPath('data.summary.clicks', 1);
    }

    public function test_ad_and_subscription_invoices_never_cancel_each_other(): void
    {
        $this->listCafe();
        $c = $this->campaign();
        $this->submitAndApprove($c['id']);
        $adPayment = $this->startPayment($c['id']);
        $this->travel(21)->minutes();

        // The owner also buys a plan meanwhile.
        $sub = $this->postJson('/api/v1/billing/checkout', ['plan_id' => Plan::query()->where('key', 'chain')->value('id'), 'cycle' => 'monthly'], $this->h())->assertCreated()->json('data');
        parse_str((string) parse_url((string) $sub['redirect_url'], PHP_URL_QUERY), $q);
        $this->postJson("/api/v1/billing/invoices/{$sub['invoice']['id']}/verify", ['authority' => $q['Authority']], $this->h())->assertJsonPath('data.paid', true);
        $this->inTenant($this->tenant, fn () => $this->assertSame('open', BillingInvoice::query()->findOrFail($adPayment['invoice'])->status));
        $period = $this->inTenant($this->tenant, fn () => Subscription::query()->firstOrFail()->current_period_end?->toIso8601String());

        // Then pays the ad: the campaign runs, the subscription is untouched.
        $this->verifyAd($adPayment)->assertJsonPath('data.paid', true);
        $this->inTenant($this->tenant, function () use ($period, $sub): void {
            $this->assertSame('paid', BillingInvoice::query()->findOrFail($sub['invoice']['id'])->status);
            $this->assertSame($period, Subscription::query()->firstOrFail()->current_period_end?->toIso8601String());
        });
        $this->getJson("/api/v1/ads/campaigns/{$c['id']}", $this->h())->assertJsonPath('data.campaign.status', 'paid');

        // A subscription invoice can't be verified through the ads route.
        $this->postJson("/api/v1/ads/invoices/{$sub['invoice']['id']}/verify", ['authority' => 'x'], $this->h())->assertNotFound();
    }

    public function test_a_payment_that_lost_its_slot_is_kept_and_sent_back_to_review(): void
    {
        $this->listCafe();
        AdPlacement::query()->where('key', 'search_top')->update(['capacity' => 1]);
        $c = $this->campaign();
        $this->submitAndApprove($c['id']);
        $payment = $this->startPayment($c['id']);

        // While at the gateway: the price can't change under the payment.
        $this->putJson("/api/v1/ads/campaigns/{$c['id']}", ['name' => 'x', 'placement' => 'search_top', 'start_date' => '2026-09-27', 'days' => 9, 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'ad_payment_in_progress');

        // …and another café pays for the last slot first.
        $this->rival('cafe-r', 'تهران', ['cafe'], 'search_top');
        $this->verifyAd($payment)->assertJsonPath('data.paid', true);
        $this->getJson("/api/v1/ads/campaigns/{$c['id']}", $this->h())
            ->assertJsonPath('data.campaign.status', 'pending')->assertJsonPath('data.campaign.payment_issue', 'capacity');
        $issues = $this->getJson('/api/v1/platform/ads?status=issues', $this->admin())->assertOk()->json('data.campaigns');
        $this->assertSame([$c['id']], array_column($issues, 'id'));
        $this->assertNotNull($issues[0]['paid_at']);
    }

    public function test_permissions_and_platform_only_routes(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $manager = $this->addMember($this->tenant, $this->owner, 'manager');
        $this->getJson('/api/v1/ads', $this->h($cashier))->assertForbidden();
        $this->getJson('/api/v1/ads', $this->h($manager))->assertOk()->assertJsonPath('data.cities', ['تهران']);

        $this->getJson('/api/v1/platform/ads', $this->h())->assertForbidden();
        $this->putJson('/api/v1/platform/ads/placements/home_banner', ['daily_price' => 1, 'capacity' => 1, 'is_active' => true], $this->h())->assertForbidden();
        $this->putJson('/api/v1/platform/ads/placements/home_banner', ['daily_price' => 2_500_000, 'capacity' => 4, 'is_active' => true], $this->admin())
            ->assertOk()->assertJsonPath('data.daily_price', 2_500_000);
        $this->assertSame(4, AdPlacement::query()->where('key', 'home_banner')->value('capacity'));

        // A switched-off placement can't be booked.
        AdPlacement::query()->where('key', 'home_banner')->update(['is_active' => false]);
        $this->postJson('/api/v1/ads/campaigns', ['name' => 'x', 'placement' => 'home_banner', 'start_date' => '2026-09-27', 'days' => 2, 'headline' => 'x', 'cta' => 'menu'], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'ad_placement_unavailable');
    }
}
