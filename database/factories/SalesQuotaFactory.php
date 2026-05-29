<?php

namespace Database\Factories;

use App\Models\SalesQuota;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesQuota>
 */
class SalesQuotaFactory extends Factory
{
    public function definition(): array
    {
        $periodType = $this->faker->randomElement(['monthly', 'quarterly', 'yearly']);
        $period = match ($periodType) {
            'monthly' => now()->format('Y-m'),
            'quarterly' => now()->year.'-Q'.ceil(now()->month / 3),
            'yearly' => (string) now()->year,
        };

        return [
            'user_id' => User::factory(),
            'product_id' => null,
            'period_type' => $periodType,
            'period' => $period,
            'target_amount' => $this->faker->randomFloat(2, 100000, 10000000),
            'created_by' => User::factory()->state(['role' => 'admin']),
        ];
    }

    public function monthly(string $period = null): static
    {
        return $this->state([
            'period_type' => 'monthly',
            'period' => $period ?? now()->format('Y-m'),
        ]);
    }

    public function quarterly(string $period = null): static
    {
        return $this->state([
            'period_type' => 'quarterly',
            'period' => $period ?? now()->year.'-Q'.ceil(now()->month / 3),
        ]);
    }

    public function yearly(string $period = null): static
    {
        return $this->state([
            'period_type' => 'yearly',
            'period' => $period ?? (string) now()->year,
        ]);
    }
}
