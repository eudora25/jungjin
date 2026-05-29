<?php

namespace App\Policies;

use App\Models\SalesQuota;
use App\Models\User;

class SalesQuotaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPharma();
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function update(User $user, SalesQuota $quota): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, SalesQuota $quota): bool
    {
        return $user->isPharma();
    }
}
