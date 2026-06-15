<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_DISCONTINUED = 'discontinued';

    public const APPROVAL_DRAFT = 'draft';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_REVIEWED = 'reviewed';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    public const DRUG_TYPE_GENERAL = 'general';

    public const DRUG_TYPE_ETC = 'etc';

    public const DRUG_TYPE_NARCOTIC = 'narcotic';

    public const DRUG_TYPE_PSYCHOTROPIC = 'psychotropic';

    /**
     * NIMS 관리대상으로 자동 분류되는 약품 유형.
     *
     * @var array<int, string>
     */
    public const NIMS_DRUG_TYPES = [
        self::DRUG_TYPE_NARCOTIC,
        self::DRUG_TYPE_PSYCHOTROPIC,
    ];

    protected $fillable = [
        'tenant_id',
        'insurance_code',
        'standard_code',
        'barcode_gtin',
        'product_code',
        'product_name',
        'generic_name',
        'strength',
        'unit',
        'pack_size',
        'manufacturer',
        'category',
        'drug_type',
        'storage_condition',
        'description',
        'image_path',
        'price',
        'status',
        'replacement_product_id',
        'approval_status',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'nims_managed',
        'nims_item_code',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pack_size' => 'integer',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'nims_managed' => 'boolean',
        ];
    }

    /**
     * 약품 유형이 마약/향정이면 NIMS 관리대상으로 자동 세팅.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if (in_array($product->drug_type, self::NIMS_DRUG_TYPES, true)) {
                $product->nims_managed = true;
            }
        });
    }

    /**
     * 플랫폼 `/platform/products/*` 전역 조회 — 임퍼서네이션 중 TenantScope 우회.
     */
    public static function platformGlobalQuery(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        $query = request()->routeIs('platform.products.*')
            ? static::platformGlobalQuery()
            : static::query();

        return $query->where($field, $value)->first();
    }

    /**
     * spatie/laravel-activitylog — 의약품 마스터 변경 이력.
     *
     * NIMS 식별 컬럼이 변경되거나 NIMS 관리 대상 제품이면 `nims.product` 채널로 분리 기록.
     * (분리 채널 결정은 `tapActivity()`에서 처리)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'insurance_code', 'standard_code', 'barcode_gtin',
                'product_code', 'product_name', 'generic_name',
                'strength', 'unit', 'pack_size', 'manufacturer',
                'category', 'drug_type', 'storage_condition',
                'price', 'status', 'replacement_product_id',
                'approval_status', 'reviewed_at', 'reviewed_by',
                'approved_at', 'approved_by',
                'nims_managed', 'nims_item_code',
                'remarks',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product')
            ->setDescriptionForEvent(fn (string $event) => "product.{$event}");
    }

    /**
     * NIMS 관련 컬럼이 변경되었거나 NIMS 관리 대상이면 별도 채널(`nims.product`)로 분기.
     * 또한 `change_reason` 컨텍스트가 있으면 properties 에 저장.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $changes = $activity->properties->toArray();
        $attrs = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        $nimsKeys = ['drug_type', 'nims_managed', 'nims_item_code'];
        $hasNimsChange = collect($nimsKeys)->some(
            fn ($k) => array_key_exists($k, $attrs) || array_key_exists($k, $old),
        );

        if ($hasNimsChange || ($this->nims_managed ?? false)) {
            $activity->log_name = 'nims.product';
        }

        if ($reason = ChangeReason::current()) {
            $props = $activity->properties->put('reason', $reason);
            $activity->properties = $props;
        }
    }

    public function commissionRates(): HasMany
    {
        return $this->hasMany(ProductCommissionRate::class)->orderByDesc('effective_from');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)
            ->orderBy('price_type')
            ->orderByDesc('effective_from');
    }

    public function companyOverrides(): HasMany
    {
        return $this->hasMany(CompanyProductOverride::class)
            ->orderByDesc('effective_from');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replacement_product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    /**
     * 판매 가능: 승인 완료 + 활성 상태.
     */
    public function scopeSalable(Builder $query): Builder
    {
        return $query
            ->where('approval_status', self::APPROVAL_APPROVED)
            ->where('status', self::STATUS_ACTIVE);
    }

    public function isSalable(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED
            && $this->status === self::STATUS_ACTIVE;
    }

    public function isNimsControlled(): bool
    {
        return (bool) $this->nims_managed
            || in_array($this->drug_type, self::NIMS_DRUG_TYPES, true);
    }

    public function currentCommissionRate(?CarbonInterface $date = null): ?ProductCommissionRate
    {
        $date ??= now();

        return $this->commissionRates()
            ->where('status', 'active')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * 특정 시점에 적용 중인 가격 1건을 반환.
     *
     *  - effective_from <= $on AND (effective_to IS NULL OR effective_to >= $on)
     *  - 같은 조건이 여러 개라면 effective_from 이 가장 최근인 것을 채택.
     */
    public function currentPriceOf(string $type = ProductPrice::TYPE_SALE, ?CarbonInterface $on = null): ?ProductPrice
    {
        $on ??= now();

        return $this->prices()
            ->ofType($type)
            ->activeOn($on)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * 가격 종류별 최신 1건씩 묶어서 반환 (대시보드/상세에서 캐시용).
     *
     * @return array<string, ProductPrice|null>
     */
    public function latestPricesByType(?CarbonInterface $on = null): array
    {
        return [
            ProductPrice::TYPE_INSURANCE => $this->currentPriceOf(ProductPrice::TYPE_INSURANCE, $on),
            ProductPrice::TYPE_COST => $this->currentPriceOf(ProductPrice::TYPE_COST, $on),
            ProductPrice::TYPE_SALE => $this->currentPriceOf(ProductPrice::TYPE_SALE, $on),
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * 특정 거래처에 적용 중인 단가 예외(override) 1건을 반환.
     */
    public function activeOverrideFor(Company $company, ?CarbonInterface $on = null): ?CompanyProductOverride
    {
        return $this->companyOverrides()
            ->forCompany($company->id)
            ->activeOn($on)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * 거래처-제품 매출 단가 해석 (가격 정책의 최종 진입점).
     *
     *  우선순위:
     *   1) company_product_overrides.override_unit_price (있으면 우선)
     *   2) product_prices(price_type=sale, 시점)
     *   3) products.price (호환성 캐시)
     */
    public function effectiveSalePriceFor(Company $company, ?CarbonInterface $on = null): ?float
    {
        $override = $this->activeOverrideFor($company, $on);
        if ($override && $override->override_unit_price !== null) {
            return (float) $override->override_unit_price;
        }

        $sale = $this->currentPriceOf(ProductPrice::TYPE_SALE, $on);
        if ($sale) {
            return (float) $sale->amount;
        }

        return $this->price !== null ? (float) $this->price : null;
    }

    /**
     * 거래처-제품 수수료율 해석.
     *
     *  우선순위:
     *   1) company_product_overrides.override_commission_rate (있으면 우선)
     *   2) product_commission_rates × companies.default_commission_grade
     */
    public function effectiveCommissionRateFor(Company $company, ?CarbonInterface $on = null): ?float
    {
        $override = $this->activeOverrideFor($company, $on);
        if ($override && $override->override_commission_rate !== null) {
            return (float) $override->override_commission_rate;
        }

        $rate = $this->currentCommissionRate($on);
        if (! $rate) {
            return null;
        }

        $grade = strtolower((string) ($company->default_commission_grade ?? 'a'));
        $col = "commission_rate_{$grade}";

        return isset($rate->{$col}) ? (float) $rate->{$col} : null;
    }
}
