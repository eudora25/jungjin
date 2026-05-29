<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::default()->id,
            'company_name' => $this->faker->company(),
            'business_registration_number' => $this->faker->numerify('##########'),
            'representative_name' => $this->faker->name(),
            'company_group' => $this->faker->randomElement(['제약사', '의료기기', '도매상']),
            'default_commission_grade' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E']),
            'postcode' => $this->faker->numerify('#####'),
            'business_address' => $this->faker->address(),
            'contact_person_name' => $this->faker->name(),
            'landline_phone' => $this->faker->numerify('02-###-####'),
            'mobile_phone' => $this->faker->numerify('010-####-####'),
            'email' => $this->faker->unique()->safeEmail(),
            'remarks' => null,
            'status' => 'active',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'approval_status' => 'pending',
            'approved_at' => null,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
