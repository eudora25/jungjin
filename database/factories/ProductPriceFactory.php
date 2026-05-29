<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_type' => ProductPrice::TYPE_SALE,
            'amount' => $this->faker->randomFloat(2, 100, 50000),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'source' => null,
            'note' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function ofType(string $type): self
    {
        return $this->state(fn () => ['price_type' => $type]);
    }

    public function effectiveFrom(string|\DateTimeInterface $date): self
    {
        return $this->state(fn () => [
            'effective_from' => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : $date,
        ]);
    }

    public function sealed(string|\DateTimeInterface $effectiveTo): self
    {
        return $this->state(fn () => [
            'effective_to' => $effectiveTo instanceof \DateTimeInterface
                ? $effectiveTo->format('Y-m-d')
                : $effectiveTo,
        ]);
    }
}
