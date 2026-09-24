<?php

namespace Database\Factories;

use App\Modules\Core\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant context (TenantContext::runAs), like every tenant-owned model.
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'شعبه '.fake()->unique()->city(),
            'slug' => fake()->unique()->slug(2),
            'city' => 'تهران',
            'province' => 'تهران',
            'is_active' => true,
        ];
    }
}
