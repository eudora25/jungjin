<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductPrice extends Model
{
    use HasFactory;
    use LogsActivity;

    public const TYPE_INSURANCE = 'insurance';

    public const TYPE_COST = 'cost';

    public const TYPE_SALE = 'sale';

    public const TYPES = [
        self::TYPE_INSURANCE,
        self::TYPE_COST,
        self::TYPE_SALE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_INSURANCE => '보험약가',
        self::TYPE_COST => '매입가',
        self::TYPE_SALE => '매출가',
    ];

    protected $fillable = [
        'product_id',
        'price_type',
        'amount',
        'effective_from',
        'effective_to',
        'source',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /* ------------------------------------------------------------------ *
     | Relations                                                          |
     * ------------------------------------------------------------------ */

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* ------------------------------------------------------------------ *
     | Scopes                                                             |
     * ------------------------------------------------------------------ */

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('price_type', $type);
    }

    /**
     * 특정 일자에 적용 중인 가격(들)을 반환.
     *  - effective_from <= $date AND (effective_to IS NULL OR effective_to >= $date)
     */
    public function scopeActiveOn(Builder $q, \DateTimeInterface|string|null $date = null): Builder
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        return $q->where('effective_from', '<=', $date)
            ->where(function ($qq) use ($date) {
                $qq->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function isActive(\DateTimeInterface|string|null $on = null): bool
    {
        $date = $on ? Carbon::parse($on)->toDateString() : now()->toDateString();
        if ($this->effective_from->gt($date)) {
            return false;
        }
        if ($this->effective_to && $this->effective_to->lt($date)) {
            return false;
        }

        return true;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->price_type] ?? $this->price_type;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['product_id', 'price_type', 'amount', 'effective_from', 'effective_to', 'source', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product.price')
            ->setDescriptionForEvent(fn (string $event) => "product.price.{$event}");
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($reason = ChangeReason::current()) {
            $props = $activity->properties->put('reason', $reason);
            $activity->properties = $props;
        }
    }
}
