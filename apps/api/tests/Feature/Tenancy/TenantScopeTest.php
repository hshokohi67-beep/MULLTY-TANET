<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Core\Models\Branch;
use App\Support\Tenancy\MissingTenantContextException;
use App\Support\Tenancy\TenantContext;
use LogicException;
use Tests\TestCase;

final class TenantScopeTest extends TestCase
{
    public function test_querying_tenant_data_without_a_context_fails_closed(): void
    {
        $this->createTenantWithOwner('cafe-a');

        $this->expectException(MissingTenantContextException::class);
        Branch::query()->get();
    }

    public function test_queries_only_see_the_current_tenant(): void
    {
        ['tenant' => $a] = $this->createTenantWithOwner('cafe-a');
        ['tenant' => $b] = $this->createTenantWithOwner('cafe-b');

        $this->inTenant($a, fn () => Branch::factory()->create(['name' => 'شعبه الف', 'slug' => 'alef']));
        $this->inTenant($b, fn () => Branch::factory()->create(['name' => 'شعبه ب', 'slug' => 'be']));

        $namesA = $this->inTenant($a, fn () => Branch::query()->pluck('name')->all());

        $this->assertContains('شعبه الف', $namesA);
        $this->assertNotContains('شعبه ب', $namesA);
        $this->assertNull($this->inTenant($a, fn () => Branch::query()->where('slug', 'be')->first()));
    }

    public function test_tenant_id_is_filled_automatically_and_cannot_target_another_tenant(): void
    {
        ['tenant' => $a] = $this->createTenantWithOwner('cafe-a');
        ['tenant' => $b] = $this->createTenantWithOwner('cafe-b');

        $branch = $this->inTenant($a, fn () => Branch::factory()->create());
        $this->assertSame($a->id, $branch->tenant_id);

        $this->expectException(LogicException::class);
        $this->inTenant($a, fn () => Branch::factory()->create(['tenant_id' => $b->id]));
    }

    public function test_tenant_id_is_immutable(): void
    {
        ['tenant' => $a] = $this->createTenantWithOwner('cafe-a');
        ['tenant' => $b] = $this->createTenantWithOwner('cafe-b');
        $branch = $this->inTenant($a, fn () => Branch::factory()->create());

        $this->expectException(LogicException::class);
        $this->inTenant($a, fn () => $branch->forceFill(['tenant_id' => $b->id])->save());
    }

    public function test_bypass_is_explicit_and_restores_scoping(): void
    {
        $this->createTenantWithOwner('cafe-a');
        $this->createTenantWithOwner('cafe-b');
        $context = app(TenantContext::class);

        $this->assertSame(2, $context->bypass(fn () => Branch::query()->count()));
        $this->assertFalse($context->isBypassed());

        $this->expectException(MissingTenantContextException::class);
        Branch::query()->count();
    }
}
