<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' 제약',
            'code' => strtoupper(fake()->unique()->bothify('TEN###')),
            'business_registration_number' => fake()->unique()->numerify('###-##-#####'),
            'status' => Tenant::STATUS_ACTIVE,
            'settings' => null,
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Tenant::STATUS_INACTIVE]);
    }
}
