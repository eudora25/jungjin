<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 테넌트 격리 글로벌 스코프. (GAP-10 MT-3)
 *
 * 현재 테넌트가 설정된 경우(admin/sales)에만 `where tenant_id = 현재테넌트` 적용.
 * 미설정(super_admin/콘솔)이면 필터하지 않음 → 전역 조회.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->hasTenant()) {
            $builder->where($model->getTable().'.tenant_id', $context->id());
        }
    }
}
