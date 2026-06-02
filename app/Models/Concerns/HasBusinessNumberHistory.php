<?php

namespace App\Models\Concerns;

use App\Models\BusinessNumberHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * 사업자등록번호 이력 보관 — 병의원·약국 등 공유 마스터에 부착.
 * 본 모델의 business_registration_number(현재 번호)와 별개로,
 * 과거 번호까지 누적 보관해 옛 번호로도 검색할 수 있게 한다.
 */
trait HasBusinessNumberHistory
{
    /** 사업자번호 이력 (현재 + 과거) */
    public function numberHistories(): MorphMany
    {
        return $this->morphMany(BusinessNumberHistory::class, 'numberable');
    }

    /** 현재 사용 중인 번호의 이력 행 */
    public function currentNumberHistory(): ?BusinessNumberHistory
    {
        return $this->numberHistories()->where('is_current', true)->first();
    }

    /**
     * 최초 등록 시 현재 번호를 이력에 시드. (이력이 이미 있으면 아무 것도 안 함)
     */
    public function seedBusinessNumberHistory(?int $userId = null): void
    {
        $number = $this->business_registration_number;

        if (! $number || $this->numberHistories()->exists()) {
            return;
        }

        $this->numberHistories()->create([
            'business_registration_number' => $number,
            'is_current' => true,
            'reason' => '최초 등록',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * 사업자번호 변경 — 과거 번호 마감(valid_to·is_current=false) + 새 번호를 현재로,
     * 본 모델의 business_registration_number 컬럼도 함께 갱신. (단일 트랜잭션)
     *
     * @param  array{valid_from?: ?string, previous_valid_to?: ?string, reason?: ?string, note?: ?string}  $meta
     */
    public function changeBusinessNumber(string $newNumber, array $meta = [], ?int $userId = null): BusinessNumberHistory
    {
        return DB::transaction(function () use ($newNumber, $meta, $userId) {
            $current = $this->numberHistories()->where('is_current', true)->first();

            if ($current) {
                $current->update([
                    'is_current' => false,
                    'valid_to' => $meta['previous_valid_to'] ?? $current->valid_to ?? now()->toDateString(),
                    'updated_by' => $userId,
                ]);
            }

            $row = $this->numberHistories()->create([
                'business_registration_number' => $newNumber,
                'is_current' => true,
                'valid_from' => $meta['valid_from'] ?? null,
                'reason' => $meta['reason'] ?? null,
                'note' => $meta['note'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->forceFill([
                'business_registration_number' => $newNumber,
                'updated_by' => $userId,
            ])->save();

            return $row;
        });
    }
}
