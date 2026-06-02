<?php

namespace App\Http\Requests\Concerns;

/**
 * 사업자등록번호 입력값을 숫자만 남겨 정규화한다. (표시 형식은 프론트에서 처리)
 * 저장·검증(유니크)이 모두 숫자만 기준으로 동작하도록 prepareForValidation 에서 호출.
 */
trait NormalizesBusinessNumber
{
    protected function normalizeBusinessNumber(string $key = 'business_registration_number'): void
    {
        if (! $this->has($key)) {
            return;
        }

        $raw = $this->input($key);
        $digits = $raw === null ? null : (preg_replace('/\D/', '', (string) $raw) ?: null);

        $this->merge([$key => $digits]);
    }
}
