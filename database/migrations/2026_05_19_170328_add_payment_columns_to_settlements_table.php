<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-5-1: 정산 지급 메타데이터 컬럼 추가.
 *
 * - paid_on : 실제 지급일(YYYY-MM-DD). `paid_at`(시스템 상태 전이 시각)과 분리
 * - payment_method : bank_transfer / cash / other
 * - payment_batch_no : 지급 묶음 식별자 (월말 N건 일괄 지급 시 동일 값 부여)
 * - payment_note : 메모
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->date('paid_on')->nullable()->after('paid_by')
                ->comment('실제 지급일');
            $table->enum('payment_method', ['bank_transfer', 'cash', 'other'])->nullable()
                ->after('paid_on')
                ->comment('지급 수단');
            $table->string('payment_batch_no', 50)->nullable()
                ->after('payment_method')
                ->comment('지급 묶음 식별자 (일괄 지급 시 공통 값)');
            $table->text('payment_note')->nullable()
                ->after('payment_batch_no')
                ->comment('지급 메모');

            $table->index('payment_batch_no', 'settlements_payment_batch_no_idx');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex('settlements_payment_batch_no_idx');
            $table->dropColumn(['paid_on', 'payment_method', 'payment_batch_no', 'payment_note']);
        });
    }
};
