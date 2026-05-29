<?php

namespace App\Policies;

use App\Models\CompanyProductOverride;
use App\Models\User;

class CompanyProductOverridePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CompanyProductOverride $override): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function update(User $user, CompanyProductOverride $override): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, CompanyProductOverride $override): bool
    {
        return $user->isPharma();
    }
}
