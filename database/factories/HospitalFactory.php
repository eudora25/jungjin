<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hospital>
 */
class HospitalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_code' => $this->faker->unique()->bothify('HP-####'),
            'hospital_name' => $this->faker->company().' 병원',
            'business_registration_number' => $this->faker->unique()->numerify('###-##-#####'),
            'hospital_type' => $this->faker->randomElement(Hospital::TYPES),
            'specialty' => $this->faker->randomElement(['내과', '외과', '소아과', '정형외과', '피부과', '안과']),
            'representative_name' => $this->faker->name(),
            'postcode' => $this->faker->numerify('#####'),
            'address' => $this->faker->address(),
            'phone' => $this->faker->numerify('02-###-####'),
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
