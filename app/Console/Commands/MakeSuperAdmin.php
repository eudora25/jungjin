<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * GAP-10 MT-6 — 플랫폼 운영자(super_admin) 계정 생성/승격.
 *
 * 사용:
 *   php artisan tenancy:make-super-admin {email} [--name=] [--password=]
 *   - 기존 사용자면 super_admin 으로 승격(tenant_id=null)
 *   - 없으면 신규 생성
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'tenancy:make-super-admin
        {email : 운영자 이메일}
        {--name= : 신규 생성 시 이름}
        {--password= : 신규 생성 시 비밀번호}';

    protected $description = '플랫폼 운영자(super_admin) 계정을 생성하거나 기존 사용자를 승격합니다.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role' => User::ROLE_SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true]);
            $this->info("기존 사용자 [{$email}] 를 super_admin 으로 승격했습니다.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: 'password';
        $user = User::create([
            'name' => $this->option('name') ?: '플랫폼 운영자',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $this->info("super_admin [{$email}] 생성 완료 (id={$user->id}).");

        return self::SUCCESS;
    }
}
