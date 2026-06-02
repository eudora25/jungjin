<?php

namespace App\Models;

/**
 * 요청 처리 중 발생한 변경에 대한 사유를 임시 보관하는 헬퍼.
 *
 *  - Form Request / Controller 에서 `ChangeReason::set('승인: …')` 호출 →
 *    이후 같은 요청 안에서 발생하는 모델 saved/updated/deleted 이벤트의
 *    activity log properties에 `reason`이 자동 첨부됨.
 *  - 요청 종료 시 자동 초기화되지 않으므로, 책임자(컨트롤러)가 명시적으로
 *    `ChangeReason::clear()` 또는 `ChangeReason::with(...)` 패턴을 사용해야 함.
 */
class ChangeReason
{
    private static ?string $current = null;

    public static function set(?string $reason): void
    {
        self::$current = $reason !== null && trim($reason) !== '' ? $reason : null;
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * 콜백 실행 동안만 사유를 활성화. 종료 시 자동 클리어.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public static function with(?string $reason, \Closure $callback)
    {
        $previous = self::$current;
        self::set($reason);
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }
}
