<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * 테넌트 종속 모델 공통 트레이트. (GAP-10 MT-3)
 *
 *  - 글로벌 스코프(TenantScope) 부착 → 현재 테넌트 자동 필터.
 *  - 생성 시 tenant_id 가 비어 있으면 현재 테넌트로 자동 주입.
 *
 * `tenant()` 관계는 각 모델에 이미 정의되어 있어 트레이트에 두지 않는다.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (empty($model->tenant_id)) {
                $context = app(TenantContext::class);
                if ($context->hasTenant()) {
                    $model->tenant_id = $context->id();
                }
            }
        });
    }
}
