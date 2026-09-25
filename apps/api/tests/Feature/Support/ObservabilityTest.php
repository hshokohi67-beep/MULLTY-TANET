<?php

namespace Tests\Feature\Support;

use Illuminate\Foundation\Events\DiagnosingHealth;
use RuntimeException;
use Tests\TestCase;

final class ObservabilityTest extends TestCase
{
    public function test_request_id_is_generated_when_absent(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithOwner('cafe-a');

        $response = $this->getJson('/api/v1/public/tenant', ['X-Tenant' => $tenant->slug]);

        $response->assertHeader('X-Request-Id');
        $this->assertNotSame('', $response->headers->get('X-Request-Id'));
    }

    public function test_incoming_request_id_is_honoured(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithOwner('cafe-a');

        $response = $this->getJson('/api/v1/public/tenant', ['X-Tenant' => $tenant->slug, 'X-Request-Id' => 'client-abc-123']);

        $response->assertHeader('X-Request-Id', 'client-abc-123');
    }

    public function test_malformed_incoming_request_id_is_replaced(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithOwner('cafe-a');
        $tooLong = str_repeat('a', 200);

        $response = $this->getJson('/api/v1/public/tenant', ['X-Tenant' => $tenant->slug, 'X-Request-Id' => $tooLong]);

        $this->assertNotSame($tooLong, $response->headers->get('X-Request-Id'));
    }

    public function test_request_id_is_present_on_error_responses_too(): void
    {
        $response = $this->getJson('/api/v1/public/tenant', ['X-Tenant' => 'no-such-tenant']);

        $response->assertHeader('X-Request-Id');
    }

    public function test_up_is_healthy_by_default(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_diagnosing_health_fails_when_the_database_is_unreachable(): void
    {
        config(['database.default' => 'a-connection-that-does-not-exist']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database is unreachable.');

        event(new DiagnosingHealth);
    }
}
