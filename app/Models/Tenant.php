<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 제약사(테넌트) — 멀티테넌시 최상위 엔티티 (GAP-10).
 * 화면·문서 표기는 "제약사". 기존 거래처(Company)와는 별개.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'code',
        'business_registration_number',
        'status',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /** 기본 제약사(code=DEFAULT) — MT-2 시드. 없으면 생성(테스트/백필 안전망). */
    public const DEFAULT_CODE = 'DEFAULT';

    public static function default(): self
    {
        return static::firstOrCreate(
            ['code' => self::DEFAULT_CODE],
            ['name' => '기본 제약사', 'status' => self::STATUS_ACTIVE],
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** 소속 사용자 (admin/sales) */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** 생성자 (super_admin) */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
