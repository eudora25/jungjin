<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\UpdateProductRequest as BaseUpdateProductRequest;

/**
 * 플랫폼(platform) 제품 수정 — 기존 Request 규칙(마약/향정 변경 사유 포함) 재사용, 권한만 platform.
 * 소속 제약사(`tenant_id`)는 수정에서 변경하지 않는다(이동은 가격/실적 정합성 위험 — 별도 절차).
 * (GAP-10 후속-A §6.8)
 */
class UpdateProductRequest extends BaseUpdateProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }
}
