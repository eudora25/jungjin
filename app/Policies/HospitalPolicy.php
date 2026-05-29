<?php

namespace App\Policies;

use App\Models\Hospital;
use App\Models\User;

class HospitalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Hospital $hospital): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPlatform(); // MT-8: 공유 마스터 직접 쓰기는 platform 전용, pharma 는 변경요청
    }

    public function update(User $user, Hospital $hospital): bool
    {
        return $user->isPlatform();
    }

    public function delete(User $user, Hospital $hospital): bool
    {
        return $user->isPlatform();
    }
}
