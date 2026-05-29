<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * 제약사(테넌트) 관리 — 플랫폼 운영자(super_admin) 전용. (GAP-10 MT-6)
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatform();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isPlatform();
    }

    public function create(User $user): bool
    {
        return $user->isPlatform();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isPlatform();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isPlatform();
    }

    /** 제약사 admin 계정 생성(위임형 D-2) */
    public function manageAdmins(User $user, Tenant $tenant): bool
    {
        return $user->isPlatform();
    }
}
