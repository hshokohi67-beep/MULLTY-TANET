<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Branch;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class BranchTest extends TestCase
{
    public function test_owner_creates_a_branch_with_persian_digit_input(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $this->postJson('/api/v1/branches', [
            'name' => 'شعبه ونک',
            'slug' => 'vanak',
            'phone' => '۰۲۱۸۸۷۷۶۶۵۵',
            'postal_code' => '۱۹۹۶۸۳۵۱۱۱',
            'city' => 'تهران',
            'latitude' => 35.757,
            'longitude' => 51.41,
        ], $this->staffHeaders($owner, $tenant))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'vanak')
            ->assertJsonPath('data.phone', '02188776655')
            ->assertJsonPath('data.postal_code', '1996835111');
    }

    public function test_validation_errors_are_persian(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $this->postJson('/api/v1/branches', ['slug' => 'Bad Slug', 'postal_code' => '123', 'latitude' => 35], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'نام الزامی است.')
            ->assertJsonPath('errors.slug.0', 'قالب شناسه‌ی نشانی معتبر نیست.')
            ->assertJsonPath('errors.postal_code.0', 'کد پستی باید 10 رقم باشد.')
            ->assertJsonPath('errors.longitude.0', 'وقتی عرض جغرافیایی وارد شده، طول جغرافیایی الزامی است.');
    }

    public function test_the_last_active_branch_cannot_be_deactivated(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $main = $this->inTenant($tenant, fn () => Branch::query()->where('slug', 'main')->firstOrFail());

        $this->putJson("/api/v1/branches/{$main->id}", ['name' => 'مرکزی', 'slug' => 'main', 'is_active' => false], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'last_active_branch');
    }

    public function test_opening_hours_sync_and_open_status(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $main = $this->inTenant($tenant, fn () => Branch::query()->where('slug', 'main')->firstOrFail());
        $headers = $this->staffHeaders($owner, $tenant);

        $this->putJson("/api/v1/branches/{$main->id}/opening-hours", ['intervals' => [
            ['weekday' => 4, 'opens_at' => '08:00', 'closes_at' => '23:00'],
            ['weekday' => 5, 'opens_at' => '18:00', 'closes_at' => '02:00'],
        ]], $headers)
            ->assertOk()
            ->assertJsonPath('data.opening_hours.0.weekday_label', 'پنجشنبه')
            ->assertJsonPath('data.opening_hours.1.overnight', true);

        // Thursday 2026-09-24 10:00 Tehran.
        $this->travelTo(CarbonImmutable::parse('2026-09-24 10:00', 'Asia/Tehran'));
        $this->getJson("/api/v1/branches/{$main->id}/open-status", $headers)->assertOk()->assertJsonPath('data.is_open', true);

        // Saturday 01:30 Tehran: still inside Friday's overnight shift.
        $this->travelTo(CarbonImmutable::parse('2026-09-26 01:30', 'Asia/Tehran'));
        $this->getJson("/api/v1/branches/{$main->id}/open-status", $headers)->assertOk()->assertJsonPath('data.is_open', true);
    }

    public function test_too_many_intervals_per_day_are_rejected(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $main = $this->inTenant($tenant, fn () => Branch::query()->where('slug', 'main')->firstOrFail());

        $intervals = array_fill(0, 4, ['weekday' => 1, 'opens_at' => '08:00', 'closes_at' => '09:00']);

        $this->putJson("/api/v1/branches/{$main->id}/opening-hours", ['intervals' => $intervals], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonPath('errors.intervals.0', 'برای هر روز حداکثر سه بازه‌ی کاری می‌توان تعریف کرد.');
    }
}
