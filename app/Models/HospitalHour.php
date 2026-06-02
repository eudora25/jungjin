<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalHour extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'parking_fee_required' => 'boolean',
        'er_day_available' => 'boolean',
        'er_night_available' => 'boolean',
        'treatment_hours' => 'array',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
