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
        return $user->isAdmin();
    }

    public function update(User $user, Hospital $hospital): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Hospital $hospital): bool
    {
        return $user->isAdmin();
    }
}
