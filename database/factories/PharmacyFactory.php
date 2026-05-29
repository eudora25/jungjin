<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pharmacy>
 */
class PharmacyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pharmacy_code' => $this->faker->unique()->bothify('PH-####'),
            'pharmacy_name' => $this->faker->company().' 약국',
            'business_registration_number' => $this->faker->unique()->numerify('###-##-#####'),
            'representative_name' => $this->faker->name(),
            'postcode' => $this->faker->numerify('#####'),
            'address' => $this->faker->address(),
            'landline_phone' => $this->faker->numerify('02-###-####'),
            'mobile_phone' => $this->faker->numerify('010-####-####'),
            'contact_person_name' => $this->faker->name(),
            'contact_phone' => $this->faker->numerify('010-####-####'),
            'email' => $this->faker->unique()->safeEmail(),
            'remarks' => null,
            'status' => 'active',
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
