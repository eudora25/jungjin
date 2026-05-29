<?php

namespace App\Tenancy;

/**
 * 현재 요청의 "현재 테넌트(제약사)" 컨텍스트. (GAP-10 MT-3)
 *
 * - admin/sales: 소속 제약사(tenant_id)가 설정됨 → TenantScope 가 자동 필터.
 * - super_admin: 설정하지 않음(=전역) → 스코프 미적용(전체 조회). (임퍼서네이션 시 명시 설정)
 * - 콘솔/큐/마이그레이션: 설정 안 됨 → 스코프 미적용.
 *
 * "설정된 경우에만 스코프 적용" 원칙 → 비-HTTP 경로가 깨지지 않는다.
 * 컨테이너 싱글톤으로 바인딩한다 (요청 단위 수명).
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    /**
     * 스코프를 일시적으로 특정 테넌트로 바꿔 실행 (super_admin 임퍼서네이션/배치 등).
     */
    public function runAs(?int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
