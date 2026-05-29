<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Settlement extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_OTHER,
    ];

    protected $fillable = [
        'settlement_no',
        'company_id',
        'period_month',
        'status',
        'line_count',
        'total_quantity',
        'total_subtotal',
        'total_commission',
        'calculated_at',
        'confirmed_at',
        'confirmed_by',
        'paid_at',
        'paid_by',
        'paid_on',
        'payment_method',
        'payment_batch_no',
        'payment_note',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_subtotal' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'calculated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
            'paid_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }

    public function paymentFiles(): HasMany
    {
        return $this->hasMany(SettlementPaymentFile::class)->orderByDesc('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'settlement_no', 'company_id', 'period_month', 'status',
                'line_count', 'total_quantity', 'total_subtotal', 'total_commission',
                'paid_on', 'payment_method', 'payment_batch_no', 'payment_note',
                'note',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('settlement')
            ->setDescriptionForEvent(fn (string $event) => "settlement.{$event}");
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName): void
    {
        if ($reason = ChangeReason::current()) {
            $activity->properties = $activity->properties->put('reason', $reason);
        }
    }

    public static function settlementNoFor(string $periodMonth, int $companyId): string
    {
        $ym = str_replace('-', '', $periodMonth);

        return sprintf('%s-%06d', $ym, $companyId);
    }
}
