<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 공통 코드 정의 — 구분 값(role 등)의 의미를 저장·조회. (GAP-10)
 */
class CodeDefinition extends Model
{
    public const GROUP_USER_ROLE = 'user_role';

    protected $fillable = [
        'group_code',
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** 상위 코드 그룹 (group_code 로 연결) */
    public function codeGroup(): BelongsTo
    {
        return $this->belongsTo(CodeGroup::class, 'group_code', 'group_code');
    }

    public function scopeGroup(Builder $query, string $groupCode): Builder
    {
        return $query->where('group_code', $groupCode)->orderBy('sort_order');
    }

    /** 특정 코드의 라벨 (없으면 코드 그대로) */
    public static function label(string $groupCode, ?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return static::query()
            ->where('group_code', $groupCode)
            ->where('code', $code)
            ->value('name') ?? $code;
    }
}
