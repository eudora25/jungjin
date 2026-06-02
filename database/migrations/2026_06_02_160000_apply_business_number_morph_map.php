<?php

use App\Models\Hospital;
use App\Models\Pharmacy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 폴리모픽 morph map 적용 — numberable_type 의 풀 클래스명을 짧은 별칭으로 전환.
 * (App\Models\Hospital → hospital, App\Models\Pharmacy → pharmacy)
 * 동시에 numberable_type 컬럼을 VARCHAR(255) → VARCHAR(64) 로 축소(인덱스 효율).
 */
return new class extends Migration
{
    private const MAP = [
        Hospital::class => 'hospital',
        Pharmacy::class => 'pharmacy',
    ];

    public function up(): void
    {
        // 기존 행의 풀 클래스명 → 별칭
        foreach (self::MAP as $class => $alias) {
            DB::table('business_number_histories')->where('numberable_type', $class)->update(['numberable_type' => $alias]);

            // spatie activity_log 에 동일 모델이 기록돼 있으면 함께 정리 (있을 때만)
            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->where('subject_type', $class)->update(['subject_type' => $alias]);
                DB::table('activity_log')->where('causer_type', $class)->update(['causer_type' => $alias]);
            }
        }

        // 별칭만 저장되므로 컬럼 길이 축소
        Schema::table('business_number_histories', function (Blueprint $table) {
            $table->string('numberable_type', 64)->comment('소유 모델 타입 (morph map 별칭: hospital | pharmacy)')->change();
        });
    }

    public function down(): void
    {
        Schema::table('business_number_histories', function (Blueprint $table) {
            $table->string('numberable_type')->comment('소유 모델 타입 (App\\Models\\Hospital | App\\Models\\Pharmacy)')->change();
        });

        foreach (self::MAP as $class => $alias) {
            DB::table('business_number_histories')->where('numberable_type', $alias)->update(['numberable_type' => $class]);

            if (Schema::hasTable('activity_log')) {
                DB::table('activity_log')->where('subject_type', $alias)->update(['subject_type' => $class]);
                DB::table('activity_log')->where('causer_type', $alias)->update(['causer_type' => $class]);
            }
        }
    }
};
