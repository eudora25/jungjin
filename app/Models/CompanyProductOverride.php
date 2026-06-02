<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CompanyProductOverride extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_id',
        'override_unit_price',
        'override_commission_rate',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'override_unit_price' => 'decimal:2',
        'override_commission_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /* ------------------------------------------------------------------ *
     | Relations                                                          |
     * ------------------------------------------------------------------ */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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

    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    public function scopeForProduct(Builder $q, int $productId): Builder
    {
        return $q->where('product_id', $productId);
    }

    public function scopeActiveOn(Builder $q, CarbonInterface|string|null $on = null): Builder
    {
        $date = $on ? Carbon::parse($on)->toDateString() : now()->toDateString();

        return $q->where('effective_from', '<=', $date)
            ->where(function ($qq) use ($date) {
                $qq->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    public function isActive(CarbonInterface|string|null $on = null): bool
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_id', 'product_id',
                'override_unit_price', 'override_commission_rate',
                'effective_from', 'effective_to', 'reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product.override')
            ->setDescriptionForEvent(fn (string $event) => "product.override.{$event}");
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $reason = $this->reason ?? ChangeReason::current();
        if ($reason) {
            $props = $activity->properties->put('reason', $reason);
            $activity->properties = $props;
        }
    }
}
