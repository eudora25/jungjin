<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->comment('시스템 사용자 (관리자 및 영업사원)');
            $table->id()->comment('사용자 PK');
            $table->string('name')->comment('성명');
            $table->string('email')->unique()->comment('로그인 이메일 (고유)');
            $table->timestamp('email_verified_at')->nullable()->comment('이메일 인증 완료 일시');
            $table->string('password')->comment('bcrypt 해시 비밀번호');
            $table->enum('role', ['admin', 'sales'])
                ->default('sales')
                ->index()
                ->comment('권한 구분: admin=관리자(CSO), sales=영업사원');
            $table->rememberToken()->comment('로그인 유지 토큰');
            $table->timestamp('created_at')->nullable()->comment('계정 생성 일시');
            $table->timestamp('updated_at')->nullable()->comment('계정 정보 수정 일시');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->comment('비밀번호 재설정 토큰');
            $table->string('email')->primary()->comment('재설정 요청 이메일');
            $table->string('token')->comment('해시된 재설정 토큰');
            $table->timestamp('created_at')->nullable()->comment('토큰 발급 일시');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->comment('Laravel 세션 (DB 세션 드라이버)');
            $table->string('id')->primary()->comment('세션 ID');
            $table->foreignId('user_id')->nullable()->index()->comment('로그인 사용자 FK (비로그인 시 NULL)');
            $table->string('ip_address', 45)->nullable()->comment('세션 IP 주소 (IPv6 포함)');
            $table->text('user_agent')->nullable()->comment('브라우저 User-Agent');
            $table->longText('payload')->comment('직렬화된 세션 페이로드');
            $table->integer('last_activity')->index()->comment('마지막 활동 시각 (unix timestamp)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
