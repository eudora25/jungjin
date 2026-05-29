<?php

namespace App\Policies;

use App\Models\Pharmacy;
use App\Models\User;

class PharmacyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pharmacy $pharmacy): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function update(User $user, Pharmacy $pharmacy): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, Pharmacy $pharmacy): bool
    {
        return $user->isPharma();
    }
}
