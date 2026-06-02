<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 기존 제약사(tenant) 사업자등록번호에서 하이픈 등 숫자 외 문자를 제거(숫자만 저장).
 * 표시 형식(XXX-XX-XXXXX)은 프론트에서 처리.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')
            ->whereNotNull('business_registration_number')
            ->orderBy('id')
            ->select('id', 'business_registration_number')
            ->each(function ($tenant) {
                $normalized = preg_replace('/\D/', '', (string) $tenant->business_registration_number);

                if ($normalized !== $tenant->business_registration_number) {
                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update(['business_registration_number' => $normalized === '' ? null : $normalized]);
                }
            });
    }

    public function down(): void
    {
        // 하이픈 제거는 비가역 — 원본 형식을 보존하지 않으므로 롤백 없음.
    }
};
