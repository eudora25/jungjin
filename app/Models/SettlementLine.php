<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementLine extends Model
{
    protected $fillable = [
        'settlement_id',
        'performance_id',
        'snapshot_unit_price',
        'snapshot_commission_rate',
        'quantity',
        'subtotal',
        'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_unit_price' => 'decimal:2',
            'snapshot_commission_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }
}
