<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // 권한 코드 값 (의미 정의는 code_definitions 테이블 group_code='user_role' 참조)
    public const ROLE_PLATFORM = 'platform'; // 정진팜 플랫폼 운영자 (구 super_admin)

    public const ROLE_PHARMA = 'pharma';     // 제약사 관리자 (구 admin)

    public const ROLE_CSO = 'cso';           // 영업 (CSO, 구 sales)

    public const ROLES = [
        self::ROLE_PLATFORM,
        self::ROLE_PHARMA,
        self::ROLE_CSO,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
        'is_active',
        'last_sign_in_at',
    ];

    /** 정진팜 플랫폼 운영자 (구 super_admin) — 소속 테넌트 없음 */
    public function isPlatform(): bool
    {
        return $this->role === self::ROLE_PLATFORM;
    }

    /** 제약사 관리자 (구 admin) */
    public function isPharma(): bool
    {
        return $this->role === self::ROLE_PHARMA;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /** 영업 (CSO, 구 sales) */
    public function isCso(): bool
    {
        return $this->role === self::ROLE_CSO;
    }

    /**
     * 현재 테넌트의 "관리자처럼" 동작하는가 (전체 조회/관리 권한).
     * - pharma: 항상 자사 관리자
     * - platform: 특정 제약사로 임퍼서네이션(진입) 중일 때만 (TenantContext 설정됨)
     * GAP-10 super_admin 임퍼서네이션.
     */
    public function managesCurrentTenant(): bool
    {
        if ($this->isPharma()) {
            return true;
        }

        return $this->isPlatform() && app(TenantContext::class)->hasTenant();
    }

    /** 소속 제약사(테넌트) — platform 은 null */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function companyAssignments(): HasMany
    {
        return $this->hasMany(CompanySalesAssignment::class);
    }

    public function assignedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_sales_assignments')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_sign_in_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
