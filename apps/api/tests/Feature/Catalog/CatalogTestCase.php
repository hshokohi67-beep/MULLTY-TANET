<?php

namespace Tests\Feature\Catalog;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Tenant;
use App\Modules\Identity\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class CatalogTestCase extends TestCase
{
    protected Tenant $tenant;

    protected User $owner;

    protected Branch $main;

    protected function setUp(): void
    {
        parent::setUp();

        ['tenant' => $this->tenant, 'owner' => $this->owner] = $this->createTenantWithOwner('cafe-a');
        $this->main = $this->inTenant($this->tenant, fn () => Branch::query()->where('slug', 'main')->firstOrFail());
    }

    /** @return array<string, string> */
    protected function headers(?User $user = null): array
    {
        return $this->staffHeaders($user ?? $this->owner, $this->tenant);
    }

    /**
     * @param  list<array{0: ?string, 1: int}>  $variants  [name, rial]
     * @param  array<string, mixed>  $extra
     */
    protected function createProduct(string $name, array $variants = [[null, 1_000_000]], array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/catalog/products', [
            'name' => $name,
            'variants' => array_map(fn (array $v) => ['name' => $v[0], 'base_price' => $v[1]], $variants),
            ...$extra,
        ], $this->headers())->assertCreated();
    }

    protected function publicMenu(string $branch = 'main'): TestResponse
    {
        return $this->getJson('/api/v1/public/menu?branch='.$branch, ['X-Tenant' => $this->tenant->slug])->assertOk();
    }
}
