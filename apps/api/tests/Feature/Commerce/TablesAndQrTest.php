<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Models\TableQrCode;
use Illuminate\Support\Facades\DB;

final class TablesAndQrTest extends CommerceTestCase
{
    public function test_qr_token_is_shown_once_and_only_its_hash_is_stored(): void
    {
        $response = $this->postJson("/api/v1/tables/{$this->table->id}/qr", [], $this->staffHeaders($this->owner, $this->tenant))->assertCreated();
        $token = $response->json('data.qr_token');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        $this->assertFalse(DB::table('table_qr_codes')->where('token_hash', $token)->exists());
        $this->assertTrue(DB::table('table_qr_codes')->where('token_hash', hash('sha256', $token))->where('is_active', true)->exists());

        $list = $this->getJson('/api/v1/tables', $this->staffHeaders($this->owner, $this->tenant))->assertOk();
        $this->assertStringNotContainsString($token, $list->getContent());
        $this->assertSame(substr($token, -4), $list->json('data.0.qr.hint'));

        // Reissuing revokes the previous code (including the one from setUp).
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], $this->publicHeaders())->assertNotFound()->assertJsonPath('code', 'qr_invalid');
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $token], $this->publicHeaders())->assertOk();
    }

    public function test_everyone_at_a_table_joins_the_same_session(): void
    {
        $first = $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], $this->publicHeaders())
            ->assertOk()
            ->assertJsonPath('data.table.label', 'میز ۱')
            ->json('data.session_token');
        $second = $this->joinTable();

        $this->assertSame($first, $second);
    }

    public function test_invalid_or_foreign_qr_tokens_are_rejected(): void
    {
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => 'not-a-token'], $this->publicHeaders())->assertNotFound()->assertJsonPath('code', 'qr_invalid');
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => str_repeat('A', 43)], $this->publicHeaders())->assertNotFound();

        // A real token of tenant A presented to tenant B is unknown there.
        $this->createTenantWithOwner('cafe-b');
        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], ['X-Tenant' => 'cafe-b'])->assertNotFound();
    }

    public function test_inactive_table_stops_accepting_scans(): void
    {
        $this->putJson("/api/v1/tables/{$this->table->id}", ['label' => 'میز ۱', 'is_active' => false], $this->staffHeaders($this->owner, $this->tenant))->assertOk();

        $this->postJson('/api/v1/public/tables/session', ['qr_token' => $this->qrToken], $this->publicHeaders())->assertNotFound();
    }

    public function test_call_waiter_with_cooldown_and_staff_acknowledgement(): void
    {
        $session = $this->joinTable();
        $headers = $this->publicHeaders(['X-Table-Session' => $session]);

        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $headers)->assertCreated()->assertJsonPath('message', 'درخواست شما به کارکنان رسید؛ به‌زودی می‌آیند.');
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $headers)->assertStatus(429)->assertJsonPath('code', 'request_cooldown');
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'request_bill'], $headers)->assertCreated();

        $waiter = $this->addMember($this->tenant, $this->owner, 'waiter');
        $open = $this->getJson('/api/v1/table-requests', $this->staffHeaders($waiter, $this->tenant))->assertOk()->json('data');
        $this->assertCount(2, $open);
        $this->assertSame('صدا زدن گارسون', $open[0]['type_label']);

        $this->postJson("/api/v1/table-requests/{$open[0]['id']}/acknowledge", [], $this->staffHeaders($waiter, $this->tenant))->assertOk()->assertJsonPath('data.status', 'acknowledged');
        $this->assertCount(1, $this->getJson('/api/v1/table-requests', $this->staffHeaders($waiter, $this->tenant))->json('data'));
    }

    public function test_closing_the_session_invalidates_its_token(): void
    {
        $session = $this->joinTable();
        $this->postJson("/api/v1/tables/{$this->table->id}/close-session", [], $this->staffHeaders($this->owner, $this->tenant))->assertNoContent();

        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $this->publicHeaders(['X-Table-Session' => $session]))
            ->assertStatus(410)->assertJsonPath('code', 'session_invalid');

        // A forged session token (right id, wrong signature) is rejected too.
        $forged = strstr($this->joinTable(), '.', true).'.forged';
        $this->postJson('/api/v1/public/tables/requests', ['type' => 'call_waiter'], $this->publicHeaders(['X-Table-Session' => $forged]))->assertStatus(410);
    }

    public function test_idle_sessions_expire_and_a_new_visit_starts(): void
    {
        $old = $this->joinTable();
        $this->travel(4)->hours();

        $this->assertNotSame($old, $this->joinTable());
        $this->assertSame(1, $this->inTenant($this->tenant, fn () => TableQrCode::query()->where('is_active', true)->count()));
    }
}
