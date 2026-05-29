<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::default()->id,
            'insurance_code' => $this->faker->unique()->numerify('#########'),
            'product_code' => $this->faker->unique()->bothify('PRD-####'),
            'product_name' => $this->faker->words(3, true),
            'manufacturer' => $this->faker->randomElement(['신일제약', '동아제약', '한미약품', '유한양행', '대웅제약']),
            'category' => $this->faker->randomElement(['진통제', '비타민', '영양제', '미네랄', '기타']),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 100, 50000),
            'status' => 'active',
            'approval_status' => 'approved',
            'remarks' => null,
        ];
    }

    public function discontinued(): self
    {
        return $this->state(fn () => ['status' => 'discontinued']);
    }
}
