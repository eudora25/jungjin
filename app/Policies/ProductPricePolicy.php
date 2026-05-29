<?php

namespace App\Policies;

use App\Models\ProductPrice;
use App\Models\User;

class ProductPricePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductPrice $price): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProductPrice $price): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProductPrice $price): bool
    {
        return $user->isAdmin();
    }
}
