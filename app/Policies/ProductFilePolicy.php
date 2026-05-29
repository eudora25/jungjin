<?php

namespace App\Policies;

use App\Models\ProductFile;
use App\Models\User;

class ProductFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductFile $file): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProductFile $file): bool
    {
        return $user->isAdmin();
    }
}
