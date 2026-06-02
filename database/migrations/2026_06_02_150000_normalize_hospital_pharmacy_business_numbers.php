<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 병의원·약국·사업자번호 이력의 사업자등록번호를 숫자만 남겨 정규화. (1회성)
 * 하이픈 유무와 무관하게 검색되도록 저장 형식을 숫자만으로 통일한다.
 * (제약사 tenants 는 별도 정규화 마이그레이션에서 이미 처리)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hospitals', 'pharmacies', 'business_number_histories'] as $table) {
            DB::table($table)
                ->whereNotNull('business_registration_number')
                ->orderBy('id')
                ->select(['id', 'business_registration_number'])
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $digits = preg_replace('/\D/', '', (string) $row->business_registration_number) ?: null;

                        if ($digits !== $row->business_registration_number) {
                            DB::table($table)->where('id', $row->id)
                                ->update(['business_registration_number' => $digits]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // 비가역 — 정규화 이전 형식(하이픈)은 복원하지 않는다.
    }
};
