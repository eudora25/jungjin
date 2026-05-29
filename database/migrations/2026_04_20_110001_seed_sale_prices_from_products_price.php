<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 기존 products.price 값을 product_prices(price_type=sale)로 1건씩 시드.
     * - 가격 정책 도입 이전에 등록된 제품의 표시용 단가를 보존.
     * - effective_from = 제품 created_at 의 날짜 (없으면 today).
     * - 동일 (product_id, sale, effective_from)이 이미 존재하면 스킵.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $rows = DB::table('products')
                ->whereNotNull('price')
                ->where('price', '>', 0)
                ->select(['id', 'price', 'created_at', 'created_by'])
                ->get();

            $insert = [];
            $now = now();

            foreach ($rows as $row) {
                $effFrom = $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->toDateString()
                    : $now->toDateString();

                $exists = DB::table('product_prices')
                    ->where('product_id', $row->id)
                    ->where('price_type', 'sale')
                    ->where('effective_from', $effFrom)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $insert[] = [
                    'product_id' => $row->id,
                    'price_type' => 'sale',
                    'amount' => $row->price,
                    'effective_from' => $effFrom,
                    'effective_to' => null,
                    'source' => 'Phase 2 시드 (products.price)',
                    'note' => null,
                    'created_by' => $row->created_by,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // chunk insert (대용량 대비)
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('product_prices')->insert($chunk);
            }
        });
    }

    public function down(): void
    {
        DB::table('product_prices')
            ->where('source', 'Phase 2 시드 (products.price)')
            ->delete();
    }
};
