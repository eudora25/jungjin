<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySalesAssignment;
use App\Models\Performance;
use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Models\ProductPrice;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Settlement\SettlementBuilder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * GAP-3 / GAP-4 / GAP-5 수동 검증용 샘플 데이터 시더.
 *
 * 멱등성: 마커 `verification.note = '[gap345-seed]'` 로 식별되는 실적/정산을 다시 만들지 않는다.
 * 재실행 시 기존 데이터를 건드리지 않고 안내문만 다시 출력한다.
 *
 * 실행: `./vendor/bin/sail artisan db:seed --class=Gap345VerificationSeeder`
 */
class Gap345VerificationSeeder extends Seeder
{
    public function run(): void
    {
        $marker = '[gap345-seed]';

        DB::transaction(function () use ($marker) {
            $admin = $this->ensureAdmin();
            $sales = $this->ensureSales();
            $company = $this->ensureApprovedCompany($admin);
            $product = $this->ensureSalableProduct($admin);
            $this->ensureSalePrice($product, $admin);
            $this->ensureCommissionRate($product);
            $this->ensureAssignment($company, $sales, $admin);

            $created = $this->ensureApprovedPerformances($company, $product, $sales, $admin, $marker);
            $settlement = $this->ensureConfirmedSettlement($company, $admin);

            $this->printSummary($admin, $sales, $company, $product, $created, $settlement, $marker);
        });
    }

    private function ensureAdmin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'admin@jungjin.test'],
            [
                'name' => '관리자',
                'password' => bcrypt('jungjin1234!'),
                'role' => 'admin',
                'is_active' => true,
            ],
        );
    }

    private function ensureSales(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'sales-demo@jungjin.test'],
            [
                'name' => '데모 영업사원',
                'password' => bcrypt('jungjin1234!'),
                'role' => 'sales',
                'is_active' => true,
            ],
        );
    }

    private function ensureApprovedCompany(User $admin): Company
    {
        return Company::query()->firstOrCreate(
            ['business_registration_number' => '999-99-99999'],
            [
                'company_name' => '[검증용] 데모 거래처',
                'representative_name' => '홍길동',
                'company_group' => 'demo',
                'partner_type' => 'pharmacy',
                'default_commission_grade' => 'a',
                'status' => 'active',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function ensureSalableProduct(User $admin): Product
    {
        return Product::query()->firstOrCreate(
            ['insurance_code' => 'GAP345-001'],
            [
                'product_code' => 'GAP345-001',
                'product_name' => '[검증용] 데모 의약품 500mg',
                'manufacturer' => '데모제약',
                'category' => '내복약',
                'strength' => '500mg',
                'unit' => '정',
                'pack_size' => 100,
                'drug_type' => 'general',
                'storage_condition' => 'room',
                'price' => 1000,
                'status' => 'active',
                'approval_status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function ensureSalePrice(Product $product, User $admin): void
    {
        ProductPrice::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'price_type' => 'sale',
                'effective_from' => '2026-01-01',
            ],
            [
                'amount' => 1000,
                'effective_to' => null,
                'source' => 'gap345 verification seeder',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function ensureCommissionRate(Product $product): void
    {
        ProductCommissionRate::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'base_month' => '2026-01',
                'effective_from' => '2026-01-01',
            ],
            [
                'effective_to' => null,
                'commission_rate_a' => 10.00,
                'commission_rate_b' => 8.00,
                'commission_rate_c' => 6.00,
                'commission_rate_d' => 4.00,
                'commission_rate_e' => 2.00,
                'status' => 'active',
            ],
        );
    }

    private function ensureAssignment(Company $company, User $sales, User $admin): void
    {
        CompanySalesAssignment::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $sales->id,
            ],
            [
                'assigned_at' => now(),
                'assigned_by' => $admin->id,
            ],
        );
    }

    /**
     * 이번 달의 첫째 주에 실적 3건을 등록·승인. 마커가 이미 있으면 skip.
     *
     * @return int 새로 만든 실적 수
     */
    private function ensureApprovedPerformances(Company $company, Product $product, User $sales, User $admin, string $marker): int
    {
        $existing = Performance::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->where('note', $marker)
            ->count();

        if ($existing >= 3) {
            return 0;
        }

        $created = 0;
        $month = now()->format('Y-m');

        for ($i = 1; $i <= 3; $i++) {
            $date = Carbon::parse($month.'-0'.($i + 2))->toDateString();

            Performance::query()->create([
                'performance_no' => Performance::nextNumberFor($date),
                'performance_date' => $date,
                'company_id' => $company->id,
                'product_id' => $product->id,
                'quantity' => 10 * $i,
                'unit_price' => 1000,
                'commission_rate' => 10.00,
                'price_source' => Performance::PRICE_SOURCE_PRODUCT_SALE,
                'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
                'note' => $marker,
                'status' => Performance::STATUS_APPROVED,
                'submitted_at' => now()->subDays(3),
                'submitted_by' => $sales->id,
                'reviewed_at' => now()->subDays(2),
                'reviewed_by' => $admin->id,
                'approved_at' => now()->subDay(),
                'approved_by' => $admin->id,
                'created_by' => $sales->id,
                'updated_by' => $admin->id,
            ]);
            $created++;
        }

        return $created;
    }

    private function ensureConfirmedSettlement(Company $company, User $admin): Settlement
    {
        $periodMonth = now()->format('Y-m');

        /** @var SettlementBuilder $builder */
        $builder = app(SettlementBuilder::class);

        $existing = Settlement::query()
            ->where('company_id', $company->id)
            ->where('period_month', $periodMonth)
            ->first();

        if ($existing) {
            // 라인이 비어있으면 재계산 (draft 일 때만 가능)
            if ($existing->status === Settlement::STATUS_DRAFT) {
                $builder->replaceLines($existing, $admin);
            }

            return $existing->fresh();
        }

        $settlement = $builder->createOrRebuild($company, $periodMonth, $admin);

        // confirmed 로 전이 — GAP-5 지급 모달 검증 가능 상태로 만들어 둠
        if ($settlement->status === Settlement::STATUS_DRAFT && $settlement->line_count > 0) {
            $settlement->update([
                'status' => Settlement::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
        }

        return $settlement->fresh();
    }

    private function printSummary(User $admin, User $sales, Company $company, Product $product, int $createdPerformances, Settlement $settlement, string $marker): void
    {
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  GAP-3 / GAP-4 / GAP-5 검증 시드 완료');
        $this->command->info('═══════════════════════════════════════════════════════════════');

        $this->command->line(sprintf('  관리자 계정    : %s (비밀번호: jungjin1234!)', $admin->email));
        $this->command->line(sprintf('  영업사원 계정  : %s (비밀번호: jungjin1234!)', $sales->email));
        $this->command->line(sprintf('  검증용 거래처  : #%d %s (등급 %s)', $company->id, $company->company_name, $company->default_commission_grade));
        $this->command->line(sprintf('  검증용 제품    : #%d %s (보험코드 %s)', $product->id, $product->product_name, $product->insurance_code));
        $this->command->line(sprintf('  실적 신규 등록 : %d 건 (마커: %s)', $createdPerformances, $marker));
        $this->command->line(sprintf('  정산           : #%d %s (status=%s, line=%d)', $settlement->id, $settlement->settlement_no, $settlement->status, $settlement->line_count));

        $this->command->newLine();
        $this->command->info('  ▶ GAP-4 담당 배정: '.url('/companies/'.$company->id));
        $this->command->info('  ▶ GAP-3 수수료 합계 (admin): '.url('/commission-summary'));
        $this->command->info('  ▶ GAP-3 본인 명세 (sales 로그인 후): '.url('/commission-summary/users/'.$sales->id.'/statement'));
        $this->command->info('  ▶ GAP-5 지급 모달: '.url('/settlements/'.$settlement->id));
        $this->command->newLine();
    }
}
