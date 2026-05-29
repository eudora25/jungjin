<?php

namespace App\Services\Products;

use App\Models\CompanyProductOverride;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompanyProductOverrideService
{
    /**
     * 신규 등록 — 트랜잭션 내에서 같은 (company, product) 의 직전 활성 이력의
     * effective_to 를 (new.effective_from - 1d) 로 자동 마감.
     */
    public function register(Product $product, array $data, ?int $userId = null): CompanyProductOverride
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $newFrom = Carbon::parse($data['effective_from']);

            $previous = $product->companyOverrides()
                ->forCompany((int) $data['company_id'])
                ->where('effective_from', '<', $newFrom->toDateString())
                ->orderByDesc('effective_from')
                ->first();

            if ($previous) {
                $sealTo = $newFrom->copy()->subDay();
                $needsSeal = $previous->effective_to === null
                    || $previous->effective_to->gt($sealTo);
                if ($needsSeal) {
                    $previous->update([
                        'effective_to' => $sealTo,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return CompanyProductOverride::create([
                'company_id' => (int) $data['company_id'],
                'product_id' => $product->id,
                'override_unit_price' => $data['override_unit_price'] ?? null,
                'override_commission_rate' => $data['override_commission_rate'] ?? null,
                'effective_from' => $newFrom->toDateString(),
                'effective_to' => $data['effective_to'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    /**
     * 부분 수정 — override_unit_price/override_commission_rate/effective_to/reason 만.
     * (company_id/product_id/effective_from 은 인접 정합성 보호 위해 변경 불가)
     */
    public function update(CompanyProductOverride $override, array $data, ?int $userId = null): CompanyProductOverride
    {
        $payload = array_intersect_key($data, array_flip([
            'override_unit_price',
            'override_commission_rate',
            'effective_to',
            'reason',
        ]));
        $payload['updated_by'] = $userId;

        $override->update($payload);

        return $override->refresh();
    }

    public function delete(CompanyProductOverride $override): void
    {
        $override->delete();
    }
}
