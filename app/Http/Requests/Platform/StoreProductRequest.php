<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\StoreProductRequest as BaseStoreProductRequest;
use Illuminate\Validation\Rule;

/**
 * 플랫폼(platform) 제품 등록 — 기존 Request 규칙 재사용, 권한만 platform.
 * 추가: 대상 제약사(`tenant_id`)를 명시 선택해야 한다(platform 은 테넌트 컨텍스트가 없으므로 자동 주입 불가).
 * (GAP-10 후속-A §6.8)
 */
class StoreProductRequest extends BaseStoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'tenant_id' => '제약사',
        ]);
    }
}
