<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::default()->id,
            // afterCreating 에서 거래처 ID 기준으로 정식 번호로 덮어씀
            'settlement_no' => 'PENDING-'.uniqid('', true),
            'company_id' => Company::factory(),
            'period_month' => '2026-04',
            'status' => Settlement::STATUS_DRAFT,
            'line_count' => 0,
            'total_quantity' => 0,
            'total_subtotal' => 0,
            'total_commission' => 0,
            'calculated_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Settlement $settlement) {
            $settlement->forceFill([
                'settlement_no' => Settlement::settlementNoFor($settlement->period_month, (int) $settlement->company_id),
            ])->saveQuietly();
        });
    }
}
