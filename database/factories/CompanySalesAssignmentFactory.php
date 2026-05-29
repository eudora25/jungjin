<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySalesAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySalesAssignment>
 */
class CompanySalesAssignmentFactory extends Factory
{
    protected $model = CompanySalesAssignment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory()->state(['role' => 'sales']),
            'assigned_at' => now(),
            'assigned_by' => null,
        ];
    }
}
