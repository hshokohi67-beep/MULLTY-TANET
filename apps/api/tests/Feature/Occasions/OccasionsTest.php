<?php

namespace Tests\Feature\Occasions;

use App\Modules\Identity\Models\User;
use App\Modules\Occasions\Actions\NotifyUpcomingOccasions;
use App\Modules\Occasions\Actions\ResolveOccasionDates;
use App\Modules\Occasions\Models\Occasion;
use App\Modules\Occasions\Models\OccasionDate;
use App\Modules\Occasions\Models\TenantOccasion;
use App\Modules\Occasions\Support\OccasionSeed;
use Carbon\CarbonImmutable;
use Tests\Feature\Commerce\CommerceTestCase;

/** The platform's calendar of occasions and what each café hears about it. */
final class OccasionsTest extends CommerceTestCase
{
    private function dateOf(string $key, int $year): ?string
    {
        return OccasionDate::query()->whereHas('occasion', fn ($q) => $q->where('key', $key))->where('year', $year)->first()?->starts_on?->toDateString();
    }

    private function upcoming(?User $user = null): array
    {
        return $this->getJson('/api/v1/occasions/upcoming', $this->staffHeaders($user ?? $this->owner, $this->tenant))->assertOk()->json('data');
    }

    public function test_dates_resolve_in_jalali_hijri_and_gregorian_and_confirmed_ones_stay(): void
    {
        $this->assertGreaterThan(30, Occasion::query()->count()); // shipped with the migration
        app(ResolveOccasionDates::class)->handle(CarbonImmutable::parse('2026-10-19', 'Asia/Tehran'));

        $this->assertSame('2026-12-21', $this->dateOf('yalda', 1405));              // 30 Azar 1405
        $this->assertSame('2027-03-21', $this->dateOf('nowruz', 1406));
        $this->assertSame('2027-03-16', $this->dateOf('chaharshanbe_suri', 1405)); // eve of the last Wednesday
        $this->assertSame('2026-11-27', $this->dateOf('black_friday', 2026));
        $this->assertSame('2025-07-05', $this->dateOf('tasua_ashura', 1447));      // Tasua 1447, as in Iran

        // A platform admin moves a Hijri date after moon sighting; resolving again keeps it.
        $date = OccasionDate::query()->whereHas('occasion', fn ($q) => $q->where('key', 'mothers_day'))->where('year', 1448)->sole();
        $admin = $this->staffHeaders(User::factory()->platformAdmin()->create());
        $this->putJson("/api/v1/platform/occasion-dates/{$date->id}", ['starts_on' => '2026-12-02'], $admin)->assertOk();
        app(ResolveOccasionDates::class)->handle(CarbonImmutable::parse('2026-10-19', 'Asia/Tehran'));
        $this->assertSame('2026-12-02', $date->fresh()?->starts_on->toDateString());
        $this->assertTrue($date->fresh()?->confirmed);

        // Seeding again never overwrites an admin's edit.
        Occasion::query()->where('key', 'yalda')->update(['title' => 'یلدای ما']);
        OccasionSeed::install();
        $this->assertSame('یلدای ما', Occasion::query()->where('key', 'yalda')->value('title'));

        $this->getJson('/api/v1/platform/occasions', $this->staffHeaders($this->owner, $this->tenant))->assertForbidden();

        // A one-off occasion with its own date, and an edit of a rule-based one (no fixed day).
        $this->postJson('/api/v1/platform/occasions', [
            'title' => 'بازی تیم ملی', 'kind' => 'national', 'tone' => 'festive', 'calendar' => 'gregorian', 'duration_days' => 1, 'lead_days' => 3,
            'is_one_off' => true, 'date' => '2026-11-10', 'ideas' => ['پخش زنده و منوی ویژه'],
        ], $admin)->assertCreated()->assertJsonPath('data.dates.0.starts_on', '2026-11-10')->assertJsonPath('data.dates.0.confirmed', true);
        $suri = Occasion::query()->where('key', 'chaharshanbe_suri')->sole();
        $this->putJson("/api/v1/platform/occasions/{$suri->id}", [
            'title' => 'چهارشنبه‌سوری', 'kind' => 'national', 'tone' => 'festive', 'calendar' => 'jalali', 'month' => 12, 'day' => null,
            'duration_days' => 1, 'lead_days' => 12, 'ideas' => ['آجیل'],
        ], $admin)->assertOk()->assertJsonPath('data.lead_days', 12);
    }

    public function test_cafe_sees_upcoming_occasions_answers_them_and_filters_kinds(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-05 12:00', 'Asia/Tehran'));
        app(ResolveOccasionDates::class)->handle();

        $items = collect($this->upcoming())->keyBy('key');
        $yalda = $items['yalda'];
        $this->assertSame(16, $yalda['days_left']);
        $this->assertTrue($yalda['in_lead']);   // 21 days ahead
        $this->assertFalse($yalda['approximate']);
        $this->assertNotEmpty($yalda['ideas']);
        $this->assertTrue($items['fathers_day']['approximate']); // Hijri, not confirmed

        $this->putJson("/api/v1/occasions/{$yalda['id']}/state", ['status' => 'planned'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertSame('planned', collect($this->upcoming())->firstWhere('key', 'yalda')['status']);

        // The café follows only national occasions.
        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['occasions.kinds' => 'national']], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertSame(['national'], collect($this->upcoming())->pluck('kind')->unique()->values()->all());

        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $this->getJson('/api/v1/occasions/upcoming', $this->staffHeaders($cashier, $this->tenant))->assertForbidden();
    }

    public function test_notified_once_never_when_dismissed_and_mourning_has_no_promotion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-20 10:00', 'Asia/Tehran')); // a few days before Tasua 1448
        app(ResolveOccasionDates::class)->handle();

        $notify = fn () => $this->inTenant($this->tenant, fn () => app(NotifyUpcomingOccasions::class)->handle());
        $first = $notify();
        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $notify()); // once per occurrence

        $mourning = collect($this->upcoming())->firstWhere('key', 'tasua_ashura');
        $this->assertNotNull($mourning);
        $this->assertSame('mourning', $mourning['tone']);
        $this->assertNull($mourning['campaign_text']);
        $this->assertNull($mourning['theme']);

        // A dismissed occurrence is never notified.
        $this->travelTo(CarbonImmutable::parse('2026-12-05 10:00', 'Asia/Tehran'));
        $yalda = collect($this->upcoming())->firstWhere('key', 'yalda');
        $this->putJson("/api/v1/occasions/{$yalda['id']}/state", ['status' => 'dismissed'], $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $notify();
        $this->assertNull($this->inTenant($this->tenant, fn () => TenantOccasion::query()->where('occasion_date_id', $yalda['id'])->value('notified_at')));
    }

    public function test_the_bell_and_the_dashboard_widget_show_unplanned_occasions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-05 12:00', 'Asia/Tehran'));
        app(ResolveOccasionDates::class)->handle();

        $alerts = collect($this->getJson('/api/v1/dashboard/alerts', $this->staffHeaders($this->owner, $this->tenant))->assertOk()->json('data'));
        $this->assertNotNull($alerts->firstWhere('type', 'occasions'));

        $widget = $this->getJson('/api/v1/dashboard/widgets/occasions', $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertSame('yalda', collect($widget->json('data.items'))->firstWhere('key', 'yalda')['key']);
    }
}
