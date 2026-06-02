<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 사업자등록번호 이력 — 병의원·약국의 현재/과거 사업자번호. (폴리모픽)
 */
class BusinessNumberHistory extends Model
{
    protected $fillable = [
        'numberable_type',
        'numberable_id',
        'business_registration_number',
        'is_current',
        'valid_from',
        'valid_to',
        'reason',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /** 사업자등록번호는 숫자만 저장 (하이픈 무관) */
    protected function businessRegistrationNumber(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : (preg_replace('/\D/', '', (string) $value) ?: null),
        );
    }

    /** 소유 엔티티 (Hospital | Pharmacy) */
    public function numberable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
