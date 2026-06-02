<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalMealSurcharge extends Model
{
    protected $fillable = [
        'hospital_id', 'type_code', 'type_name', 'general_meal_surcharge', 'calc_headcount', 'therapeutic_meal_grade',
    ];

    protected $casts = [
        'general_meal_surcharge' => 'boolean',
        'calc_headcount' => 'integer',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
