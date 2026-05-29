<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\StorePharmacyRequest as BaseStorePharmacyRequest;

/**
 * 플랫폼(super_admin) 약국 등록 — 규칙은 기존 Request 재사용, 권한만 super_admin. (GAP-10 MT-6 마스터 CRUD)
 */
class StorePharmacyRequest extends BaseStorePharmacyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }
}
