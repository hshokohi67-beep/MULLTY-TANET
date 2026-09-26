<?php

namespace Tests\Feature\Messaging;

use App\Modules\Core\Models\TenantSetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Loyalty\Actions\GiveBirthdayGifts;
use App\Modules\Messaging\Actions\RunSmsCampaigns;
use App\Modules\Messaging\Models\SmsAccount;
use App\Modules\Messaging\Models\SmsLog;
use App\Support\Localization\JalaliDate;
use App\Support\Sms\Providers\ArraySmsProvider;
use App\Support\Sms\SmsProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Commerce\CommerceTestCase;

/**
 * The café's own SMS centre: its line carries every customer message (never the platform line),
 * automatic messages only when switched on, campaigns only to opted-in customers.
 */
final class SmsCentreTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00', 'Asia/Tehran'));
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1, 'data' => ['packId' => 'p1']])]);
    }

    /** @return array<string, string> */
    private function h(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    private function connect(): void
    {
        $this->putJson('/api/v1/sms/account', ['provider' => 'smsir', 'fields' => ['api_key' => 'SECRET-SMS-KEY-9876', 'sender' => '30004505'], 'is_active' => true], $this->h())->assertOk();
    }

    /** @return list<array<string, mixed>> */
    private function logs(): array
    {
        return $this->getJson('/api/v1/sms/logs', $this->h())->assertOk()->json('data');
    }

    public function test_connecting_a_panel_keeps_the_key_secret_and_a_test_message_verifies_it(): void
    {
        $this->putJson('/api/v1/sms/account', ['provider' => 'smsir', 'fields' => ['api_key' => '', 'sender' => '3000'], 'is_active' => true], $this->h())
            ->assertStatus(422)->assertJsonPath('code', 'sms_missing_field');
        $this->connect();

        $res = $this->getJson('/api/v1/sms', $this->h())->assertOk();
        $this->assertSame('••••9876', $res->json('data.account.fields.api_key.masked'));
        $this->assertNull($res->json('data.account.fields.api_key.value'));
        $this->assertSame('30004505', $res->json('data.account.fields.sender.value'));
        $this->assertStringNotContainsString('SECRET-SMS-KEY', (string) $res->getContent());
        $this->inTenant($this->tenant, fn () => $this->assertStringNotContainsString('SECRET-SMS-KEY', (string) SmsAccount::query()->toBase()->value('credentials')));

        // Changing only the sender keeps the stored key.
        $this->putJson('/api/v1/sms/account', ['provider' => 'smsir', 'fields' => ['api_key' => '', 'sender' => '30009999'], 'is_active' => true], $this->h())->assertOk();
        $this->inTenant($this->tenant, fn () => $this->assertSame('SECRET-SMS-KEY-9876', SmsAccount::query()->firstOrFail()->credentials['api_key']));

        $this->postJson('/api/v1/sms/account/test', ['phone' => '09121234567'], $this->h())->assertOk()->assertJsonPath('data.sent', true);
        $this->assertNotNull($this->getJson('/api/v1/sms', $this->h())->json('data.account.verified_at'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('x-api-key', 'SECRET-SMS-KEY-9876') && $r['mobiles'] === ['09121234567']);
        $this->assertSame('0912***4567', $this->logs()[0]['recipient']);
    }

    public function test_order_ready_goes_through_the_cafe_line_only_when_switched_on(): void
    {
        $platform = new ArraySmsProvider;
        $this->app->instance(SmsProvider::class, $platform);
        [, $token] = $this->customer('+989121112233');
        $auth = ['Authorization' => "Bearer {$token}"];
        $place = function () use ($auth): string {
            $cart = $this->cart('takeaway', $auth);
            $this->addItem($cart, $this->variant($this->espresso), 1, [], $auth)->assertCreated();

            return (string) $this->checkout($cart, [], $auth)->assertCreated()->json('data.id');
        };
        $ready = function (string $order): void {
            foreach (['accepted', 'preparing', 'ready'] as $s) {
                $this->postJson("/api/v1/orders/{$order}/status", ['status' => $s], $this->h())->assertOk();
            }
        };

        // Off by default: nothing sent.
        $this->connect();
        $ready($place());
        $this->assertSame([], $this->logs());

        $this->putJson('/api/v1/sms/templates', ['templates' => ['order_ready' => ['enabled' => true, 'body' => '{name}، سفارش {number} آماده است. {cafe}']]], $this->h())->assertOk();
        $ready($place());
        $log = $this->logs()[0];
        $this->assertSame(['order_ready', 'sent'], [$log['kind'], $log['status']]);
        $this->assertStringContainsString('مشتری نمونه، سفارش', $log['body']);
        $this->assertStringContainsString($this->tenant->name, $log['body']);

        // Line disconnected: logged as skipped, never sent through the platform line.
        $this->putJson('/api/v1/sms/account', ['provider' => 'smsir', 'fields' => ['api_key' => '', 'sender' => '30004505'], 'is_active' => false], $this->h())->assertOk();
        $ready($place());
        $this->assertSame(['skipped', 'not_connected'], [$this->logs()[0]['status'], $this->logs()[0]['error']]);
        $this->assertSame([], $platform->messages);
    }

    public function test_birthday_message_moved_to_the_cafe_line(): void
    {
        $platform = new ArraySmsProvider;
        $this->app->instance(SmsProvider::class, $platform);
        $this->connect();
        $this->putJson('/api/v1/sms/templates', ['templates' => ['birthday' => ['enabled' => true, 'body' => 'تولدت مبارک {name}! {gift} هدیه‌ی {cafe}']]], $this->h())->assertOk();
        ['month' => $m, 'day' => $d] = JalaliDate::toJalali(CarbonImmutable::now('Asia/Tehran'), 'Asia/Tehran');
        $this->inTenant($this->tenant, function () use ($m, $d): void {
            foreach (['loyalty.enabled' => '1', 'loyalty.birthday_wallet_gift' => '500000'] as $k => $v) {
                TenantSetting::query()->updateOrCreate(['key' => $k], ['value' => $v]);
            }
            Customer::query()->create(['phone_e164' => '+989127654321', 'name' => 'سارا', 'birth_month' => $m, 'birth_day' => $d]);
            $this->assertSame(1, app(GiveBirthdayGifts::class)->handle());
        });

        $log = $this->logs()[0];
        $this->assertSame(['birthday', 'sent'], [$log['kind'], $log['status']]);
        $this->assertStringContainsString('تولدت مبارک سارا', $log['body']);
        $this->assertSame([], $platform->messages);
    }

    public function test_campaigns_reach_only_opted_in_customers_within_quiet_hours(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Customer::query()->create(['phone_e164' => '+989120000101', 'name' => 'الف', 'marketing_opt_in' => true, 'birth_month' => 7]);
            Customer::query()->create(['phone_e164' => '+989120000102', 'name' => 'ب', 'marketing_opt_in' => true, 'birth_month' => 3]);
            Customer::query()->create(['phone_e164' => '+989120000103', 'name' => 'ج', 'marketing_opt_in' => false, 'birth_month' => 7]);
        });
        $this->postJson('/api/v1/sms/audience', ['audience' => []], $this->h())->assertOk()->assertJsonPath('data.count', 2);
        $this->postJson('/api/v1/sms/audience', ['audience' => ['birth_month' => 7]], $this->h())->assertJsonPath('data.count', 1);

        $id = $this->postJson('/api/v1/sms/campaigns', ['name' => 'پاییز', 'body' => 'لاته‌ی پاییزی ۲۰٪ تخفیف', 'audience' => []], $this->h())->assertCreated()->json('data.id');
        // No line yet.
        $this->postJson("/api/v1/sms/campaigns/{$id}/schedule", [], $this->h())->assertStatus(422)->assertJsonPath('code', 'sms_not_connected');
        $this->connect();
        $this->postJson("/api/v1/sms/campaigns/{$id}/schedule", [], $this->h())->assertOk()->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.recipients', 2);

        // 22:00 Tehran: quiet hours, nothing goes out.
        $this->travelTo(CarbonImmutable::parse('2026-09-26 22:00', 'Asia/Tehran'));
        $this->assertSame(0, app(RunSmsCampaigns::class)->handle());
        $this->travelTo(CarbonImmutable::parse('2026-09-27 08:05', 'Asia/Tehran'));
        $this->assertSame(2, app(RunSmsCampaigns::class)->handle());

        $c = collect($this->getJson('/api/v1/sms', $this->h())->json('data.campaigns'))->firstWhere('id', $id);
        $this->assertSame(['done', 2, 0], [$c['status'], $c['sent'], $c['failed']]);
        Http::assertSent(fn (Request $r) => $r['mobiles'] === ['09120000101', '09120000102'] && str_ends_with((string) $r['messageText'], "\nلغو۱۱"));
        $this->assertSame(0, app(RunSmsCampaigns::class)->handle()); // done stays done

        // A sent campaign is frozen.
        $this->putJson("/api/v1/sms/campaigns/{$id}", ['name' => 'x', 'body' => 'x', 'audience' => []], $this->h())->assertStatus(422)->assertJsonPath('code', 'sms_campaign_locked');
        $this->inTenant($this->tenant, fn () => $this->assertSame(2, SmsLog::query()->where('kind', 'campaign')->count()));
    }

    public function test_only_permitted_staff_open_the_sms_centre(): void
    {
        $cashier = $this->addMember($this->tenant, $this->owner, 'cashier');
        $manager = $this->addMember($this->tenant, $this->owner, 'manager');
        $this->getJson('/api/v1/sms', $this->h($cashier))->assertForbidden();
        $this->getJson('/api/v1/sms', $this->h($manager))->assertOk()->assertJsonPath('data.account', null)->assertJsonCount(5, 'data.drivers');
    }
}
