<?php

namespace Database\Factories;

use App\Modules\Core\Enums\TenantStatus;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'کافه '.fake()->unique()->lastName(),
            'slug' => 'cafe-'.fake()->unique()->bothify('????##'),
            'status' => TenantStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
