<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCommissionRate extends Model
{
    protected $fillable = [
        'product_id',
        'base_month',
        'commission_rate_a',
        'commission_rate_b',
        'commission_rate_c',
        'commission_rate_d',
        'commission_rate_e',
        'effective_from',
        'effective_to',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate_a' => 'decimal:2',
            'commission_rate_b' => 'decimal:2',
            'commission_rate_c' => 'decimal:2',
            'commission_rate_d' => 'decimal:2',
            'commission_rate_e' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rateForGrade(?string $grade): ?float
    {
        if (! in_array($grade, ['A', 'B', 'C', 'D', 'E'], true)) {
            return null;
        }

        return (float) $this->{'commission_rate_'.strtolower($grade)};
    }
}
