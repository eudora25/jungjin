<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 병의원·약국·사업자번호 이력의 사업자등록번호를 숫자만 남겨 정규화. (1회성)
 * 하이픈 유무와 무관하게 검색되도록 저장 형식을 숫자만으로 통일한다.
 * 정규화 시 기존 번호와 충돌(중복 데이터)하는 행은 원본을 유지하고 로그만 남긴다(운영자 수동 정리).
 * (제약사 tenants 는 별도 정규화 마이그레이션에서 이미 처리)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hospitals', 'pharmacies', 'business_number_histories'] as $table) {
            $skipped = [];

            DB::table($table)
                ->whereNotNull('business_registration_number')
                ->orderBy('id')
                ->select(['id', 'business_registration_number'])
                ->chunkById(500, function ($rows) use ($table, &$skipped) {
                    foreach ($rows as $row) {
                        $digits = preg_replace('/\D/', '', (string) $row->business_registration_number) ?: null;

                        if ($digits === $row->business_registration_number) {
                            continue;
                        }

                        try {
                            DB::table($table)->where('id', $row->id)
                                ->update(['business_registration_number' => $digits]);
                        } catch (UniqueConstraintViolationException) {
                            // 정규화하면 다른 행과 중복 → 원본 유지(운영자가 중복 데이터 수동 정리)
                            $skipped[] = $row->id;
                        }
                    }
                });

            if ($skipped !== []) {
                Log::warning("[normalize biz no] {$table}: 사업자번호 정규화 충돌로 건너뜀 — id ".implode(',', $skipped));
            }
        }
    }

    public function down(): void
    {
        // 비가역 — 정규화 이전 형식(하이픈)은 복원하지 않는다.
    }
};
