<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 사업자등록번호 이력 — 병의원·약국의 현재/과거 사업자번호를 폴리모픽으로 보관.
 * 사업자번호는 폐업·재등록 등으로 바뀔 수 있으므로, 본 테이블에 변경 이력을 누적한다.
 * 옛 번호로 검색해도 현재 그 번호를 쓰는(또는 과거에 썼던) 병의원·약국을 찾을 수 있게 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_number_histories', function (Blueprint $table) {
            $table->comment('사업자등록번호 이력 — 병의원·약국의 현재/과거 사업자번호 보관(폴리모픽)');
            $table->id()->comment('이력 PK');
            $table->string('numberable_type')->comment('소유 모델 타입 (App\\Models\\Hospital | App\\Models\\Pharmacy)');
            $table->unsignedBigInteger('numberable_id')->comment('소유 모델 ID');
            $table->string('business_registration_number', 20)->comment('사업자등록번호 (입력 형식 그대로 저장)');
            $table->boolean('is_current')->default(false)->comment('현재 사용 중인 번호 여부');
            $table->date('valid_from')->nullable()->comment('적용 시작일 (모르면 null)');
            $table->date('valid_to')->nullable()->comment('적용 종료일(폐업일 등) — 현재 번호면 null');
            $table->string('reason')->nullable()->comment('변경 사유 (예: 폐업 후 재등록)');
            $table->text('note')->nullable()->comment('비고');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('생성자(users.id)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('수정자(users.id)');
            $table->timestamps();

            $table->index(['numberable_type', 'numberable_id'], 'bnh_numberable_index');
            $table->index('business_registration_number', 'bnh_brn_index');
            $table->unique(['numberable_type', 'numberable_id', 'business_registration_number'], 'bnh_entity_number_unique');
        });

        // 기존 병의원·약국의 현재 사업자번호를 이력으로 백필 (is_current=true)
        $now = now();
        foreach (['App\\Models\\Hospital' => 'hospitals', 'App\\Models\\Pharmacy' => 'pharmacies'] as $type => $tableName) {
            DB::table($tableName)
                ->whereNull('deleted_at')
                ->whereNotNull('business_registration_number')
                ->where('business_registration_number', '!=', '')
                ->orderBy('id')
                ->select(['id', 'business_registration_number'])
                ->chunk(500, function ($rows) use ($type, $now) {
                    $insert = [];
                    foreach ($rows as $r) {
                        $insert[] = [
                            'numberable_type' => $type,
                            'numberable_id' => $r->id,
                            'business_registration_number' => $r->business_registration_number,
                            'is_current' => true,
                            'valid_from' => null,
                            'valid_to' => null,
                            'reason' => '최초 등록(백필)',
                            'note' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('business_number_histories')->insertOrIgnore($insert);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_number_histories');
    }
};
