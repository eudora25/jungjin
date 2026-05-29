<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyProductOverride;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyProductOverride>
 */
class CompanyProductOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'override_unit_price' => $this->faker->randomFloat(2, 100, 50000),
            'override_commission_rate' => $this->faker->randomFloat(2, 1, 30),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'reason' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function priceOnly(): self
    {
        return $this->state(fn () => ['override_commission_rate' => null]);
    }

    public function commissionOnly(): self
    {
        return $this->state(fn () => ['override_unit_price' => null]);
    }
}
