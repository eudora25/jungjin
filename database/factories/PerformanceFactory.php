<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Performance>
 */
class PerformanceFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-2 months', 'now');
        $qty = $this->faker->numberBetween(1, 50);
        $unit = $this->faker->randomFloat(2, 1000, 100000);

        return [
            'performance_no' => Performance::nextNumberFor($date),
            'performance_date' => $date->format('Y-m-d'),
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $unit,
            'commission_rate' => $this->faker->optional(0.8)->randomFloat(2, 1, 25),
            'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
            'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
            'price_override_id' => null,
            'price_id' => null,
            'commission_rate_id' => null,
            'note' => null,
            'status' => Performance::STATUS_DRAFT,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => Performance::STATUS_APPROVED,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
        ]);
    }

    public function submitted(): self
    {
        return $this->state(fn () => [
            'status' => Performance::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function forCompany(Company $company): self
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function forProduct(Product $product): self
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }
}
