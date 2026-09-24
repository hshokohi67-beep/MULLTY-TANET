<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\TenantSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SettingsAndBrandingTest extends TestCase
{
    public function test_secret_settings_are_encrypted_masked_and_redacted_in_audit(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $headers = $this->staffHeaders($owner, $tenant);

        $response = $this->patchJson('/api/v1/tenant/settings', ['settings' => [
            'integrations.sms.kavenegar_api_key' => 'SECRET-KEY-123456',
            'orders.allow_preorder_when_closed' => false,
        ]], $headers)->assertOk();

        $secret = collect($response->json('data'))->firstWhere('key', 'integrations.sms.kavenegar_api_key');
        $this->assertNull($secret['value']);
        $this->assertTrue($secret['is_set']);
        $this->assertSame('••••••••3456', $secret['masked']);
        $this->assertFalse(collect($response->json('data'))->firstWhere('key', 'orders.allow_preorder_when_closed')['value']);

        // Never plaintext in the database or the response, never in the audit log.
        $raw = DB::table('tenant_settings')->where('key', 'integrations.sms.kavenegar_api_key')->value('value');
        $this->assertStringNotContainsString('SECRET-KEY', (string) $raw);
        $this->assertStringNotContainsString('SECRET-KEY', $response->getContent());
        $this->assertSame('SECRET-KEY-123456', $this->inTenant($tenant, fn () => TenantSetting::query()->where('key', 'integrations.sms.kavenegar_api_key')->first()->plainValue()));

        $audit = $this->inTenant($tenant, fn () => AuditLog::query()->where('action', 'settings.updated')->latest('created_at')->first());
        $this->assertStringNotContainsString('SECRET-KEY', (string) json_encode($audit->changes));
        $this->assertSame('[REDACTED]', $audit->changes['integrations.sms.kavenegar_api_key']);
    }

    public function test_unknown_setting_keys_are_rejected(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $this->patchJson('/api/v1/tenant/settings', ['settings' => ['evil.key' => 'x']], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonPath('errors.settings.0', 'تنظیم «evil.key» وجود ندارد.');
    }

    public function test_logo_upload_is_stored_under_the_tenant_prefix(): void
    {
        Storage::fake('public');
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $response = $this->post('/api/v1/tenant/branding/logo', [
            'logo' => UploadedFile::fake()->image('../../evil name.png', 256, 256),
        ], $this->staffHeaders($owner, $tenant))->assertOk();

        $files = Storage::disk('public')->allFiles("tenants/{$tenant->id}/branding");
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^tenants/'.$tenant->id.'/branding/[0-9A-Z]{26}\.png$#', $files[0]);
        $this->assertStringContainsString($files[0], (string) $response->json('data.logo_url'));
    }

    public function test_svg_and_disguised_files_are_rejected(): void
    {
        Storage::fake('public');
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');
        $headers = $this->staffHeaders($owner, $tenant);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/api/v1/tenant/branding/logo', ['logo' => $svg], $headers)->assertUnprocessable()->assertJsonValidationErrors('logo');

        $php = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "pwned";');
        $this->post('/api/v1/tenant/branding/logo', ['logo' => $php], $headers)->assertUnprocessable()->assertJsonValidationErrors('logo');

        $huge = UploadedFile::fake()->image('big.png', 256, 256)->size(3000);
        $this->post('/api/v1/tenant/branding/logo', ['logo' => $huge], $headers)->assertUnprocessable()->assertJsonValidationErrors('logo');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_branding_update_validation(): void
    {
        ['tenant' => $tenant, 'owner' => $owner] = $this->createTenantWithOwner('cafe-a');

        $this->patchJson('/api/v1/tenant/branding', ['primary_color' => '#0f766e', 'seo_title' => 'کافه الف | بهترین قهوه'], $this->staffHeaders($owner, $tenant))
            ->assertOk()
            ->assertJsonPath('data.seo_title', 'کافه الف | بهترین قهوه');

        $this->patchJson('/api/v1/tenant/branding', ['primary_color' => 'red'], $this->staffHeaders($owner, $tenant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_color');
    }
}
