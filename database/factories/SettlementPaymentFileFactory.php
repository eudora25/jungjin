<?php

namespace Database\Factories;

use App\Models\Settlement;
use App\Models\SettlementPaymentFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettlementPaymentFile>
 */
class SettlementPaymentFileFactory extends Factory
{
    protected $model = SettlementPaymentFile::class;

    public function definition(): array
    {
        return [
            'settlement_id' => Settlement::factory(),
            'original_name' => 'receipt.pdf',
            'stored_name' => $this->faker->uuid().'.pdf',
            'path' => 'settlement-payment-files/'.$this->faker->uuid().'.pdf',
            'size' => $this->faker->numberBetween(1000, 100000),
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'uploaded_by' => User::factory(),
        ];
    }
}
