<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 공통 코드 그룹 — code_definitions.group_code 의 상위 정의. (GAP-10)
 */
class CodeGroup extends Model
{
    protected $fillable = [
        'group_code',
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

    /** 그룹에 속한 코드 정의 (group_code 로 연결) */
    public function definitions(): HasMany
    {
        return $this->hasMany(CodeDefinition::class, 'group_code', 'group_code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
