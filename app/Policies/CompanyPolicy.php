<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isPharma();
    }

    public function restore(User $user, Company $company): bool
    {
        return $user->isPharma();
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $user->isPharma();
    }
}
