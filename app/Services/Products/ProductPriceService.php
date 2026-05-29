<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductPriceService
{
    /**
     * 가격 신규 등록.
     *  - 트랜잭션 내에서 직전 활성 레코드의 effective_to 를 (new.effective_from - 1d) 로 자동 마감.
     *  - 동일 (product_id, price_type, effective_from)은 DB unique 제약으로 차단 (Form Request 에서 사전 검증).
     */
    public function register(Product $product, array $data, ?int $userId = null): ProductPrice
    {
        return DB::transaction(function () use ($product, $data, $userId) {
            $type = $data['price_type'];
            $newFrom = Carbon::parse($data['effective_from']);

            $previous = $product->prices()
                ->ofType($type)
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

            return ProductPrice::create([
                'product_id' => $product->id,
                'price_type' => $type,
                'amount' => $data['amount'],
                'effective_from' => $newFrom->toDateString(),
                'effective_to' => $data['effective_to'] ?? null,
                'source' => $data['source'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    /**
     * 가격 부분 수정. amount/source/note/effective_to 만 허용.
     *  - effective_from/price_type 은 인접 레코드와의 정합성을 무너뜨릴 수 있어 변경 불가 (삭제 후 재등록 권장).
     */
    public function update(ProductPrice $price, array $data, ?int $userId = null): ProductPrice
    {
        $payload = array_intersect_key(
            $data,
            array_flip(['amount', 'source', 'note', 'effective_to']),
        );
        $payload['updated_by'] = $userId;

        $price->update($payload);

        return $price->refresh();
    }

    /**
     * 가격 삭제. 단순 삭제만 수행하고 직전 레코드의 effective_to 자동 복원은 하지 않음.
     *  (이력의 의미상 누가 언제 어떻게 마감했는지 추적이 어렵기 때문 — 운영자가 직접 후처리)
     */
    public function delete(ProductPrice $price): void
    {
        $price->delete();
    }
}
