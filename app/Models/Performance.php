<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * 실적(Performance) — 원장(ledger).
 *
 * 설계 원칙:
 *  1) 스냅샷: `unit_price` / `commission_rate` 는 저장 시점의 해석 결과를 값으로 고정한다.
 *  2) 출처 추적: `price_source` / `commission_source` + 연관 FK 로 "어디서 왔는지" 를 항상 알 수 있다.
 *  3) 상태 머신: draft → submitted → reviewed → approved (+rejected/cancelled). 제품 워크플로와 동일.
 *
 * 스냅샷 해석은 서비스(`App\Services\Performance\PerformanceResolver`)에서 담당한다(P4-S2).
 */
class Performance extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_REVIEWED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const PRICE_SOURCE_OVERRIDE = 'override';

    public const PRICE_SOURCE_PRODUCT_SALE = 'product_sale';

    public const PRICE_SOURCE_PRODUCTS_PRICE = 'products_price';

    public const PRICE_SOURCE_MANUAL = 'manual';

    public const COMMISSION_SOURCE_OVERRIDE = 'override';

    public const COMMISSION_SOURCE_MATRIX = 'matrix';

    public const COMMISSION_SOURCE_MANUAL = 'manual';

    public const COMMISSION_SOURCE_NONE = 'none';

    protected $fillable = [
        'tenant_id',
        'performance_no',
        'performance_date',
        'company_id',
        'product_id',
        'quantity',
        'unit_price',
        'commission_rate',
        'price_source',
        'commission_source',
        'price_override_id',
        'price_id',
        'commission_rate_id',
        'note',
        'status',
        'submitted_at', 'submitted_by',
        'reviewed_at', 'reviewed_by',
        'approved_at', 'approved_by',
        'rejected_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'performance_date' => 'date',
            'unit_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            // subtotal / commission_amount 은 DB 가상 컬럼 (decimal:2 문자열로 오기 때문에 캐스트 동일)
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ *
     | Relations                                                          |
     * ------------------------------------------------------------------ */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceOverride(): BelongsTo
    {
        return $this->belongsTo(CompanyProductOverride::class, 'price_override_id');
    }

    public function priceRecord(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'price_id');
    }

    public function commissionRateRecord(): BelongsTo
    {
        return $this->belongsTo(ProductCommissionRate::class, 'commission_rate_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PerformanceFile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ------------------------------------------------------------------ *
     | Scopes                                                             |
     * ------------------------------------------------------------------ */

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopeDraft(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_DRAFT);
    }

    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    public function scopeForProduct(Builder $q, int $productId): Builder
    {
        return $q->where('product_id', $productId);
    }

    /**
     * 특정 월에 속한 실적(performance_date 기준).
     *
     * @param  string  $month  YYYY-MM
     */
    public function scopeForMonth(Builder $q, string $month): Builder
    {
        [$y, $m] = explode('-', $month);
        $start = Carbon::createFromDate((int) $y, (int) $m, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate((int) $y, (int) $m, 1)->endOfMonth()->toDateString();

        return $q->whereBetween('performance_date', [$start, $end]);
    }

    /* ------------------------------------------------------------------ *
     | Helpers                                                            |
     * ------------------------------------------------------------------ */

    public function isLocked(): bool
    {
        // approved 이후에는 원장 수정 금지 (정산 연결 전이라도 동일)
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_CANCELLED], true);
    }

    public function isRejectable(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_REVIEWED], true);
    }

    /**
     * 다음 실적 번호 생성 (YYYYMMDD-NNNN).
     * 동시성은 트랜잭션 + 유니크 인덱스로 1차 방어. 충돌 시 재시도는 호출부에서.
     */
    public static function nextNumberFor(\DateTimeInterface|string|null $date = null): string
    {
        $d = $date ? Carbon::parse($date) : now();
        $prefix = $d->format('Ymd');
        // performance_no 는 전역 unique → 테넌트 스코프를 우회해 전체 기준 최댓값으로 채번 (GAP-10 MT-3)
        $last = static::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->where('performance_no', 'like', $prefix.'-%')
            ->orderByDesc('performance_no')
            ->lockForUpdate()
            ->value('performance_no');

        $next = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    /* ------------------------------------------------------------------ *
     | Activity Log                                                       |
     * ------------------------------------------------------------------ */

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'performance_no', 'performance_date',
                'company_id', 'product_id', 'quantity',
                'unit_price', 'commission_rate',
                'price_source', 'commission_source',
                'note', 'status', 'rejected_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('performance')
            ->setDescriptionForEvent(fn (string $event) => "performance.{$event}");
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($reason = ChangeReason::current()) {
            $props = $activity->properties->put('reason', $reason);
            $activity->properties = $props;
        }
    }
}
