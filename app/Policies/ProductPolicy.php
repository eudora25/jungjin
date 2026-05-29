<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isPharma();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isPharma();
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isPharma();
    }

    /**
     * 검수 요청 (제출): draft 또는 rejected 상태에서만 가능.
     */
    public function submit(User $user, Product $product): bool
    {
        return $user->isPharma()
            && in_array($product->approval_status, [
                Product::APPROVAL_DRAFT,
                Product::APPROVAL_REJECTED,
            ], true);
    }

    /**
     * 검수 완료: pending 상태에서만 가능.
     */
    public function review(User $user, Product $product): bool
    {
        return $user->isPharma()
            && $product->approval_status === Product::APPROVAL_PENDING;
    }

    /**
     * 최종 승인: reviewed 상태에서만 가능.
     */
    public function approve(User $user, Product $product): bool
    {
        return $user->isPharma()
            && $product->approval_status === Product::APPROVAL_REVIEWED;
    }

    /**
     * 반려: pending 또는 reviewed 상태에서만 가능.
     */
    public function reject(User $user, Product $product): bool
    {
        return $user->isPharma()
            && in_array($product->approval_status, [
                Product::APPROVAL_PENDING,
                Product::APPROVAL_REVIEWED,
            ], true);
    }

    /**
     * 단종 처리: 이미 단종된 제품이 아니면 가능.
     */
    public function discontinue(User $user, Product $product): bool
    {
        return $user->isPharma()
            && $product->status !== Product::STATUS_DISCONTINUED;
    }
}
