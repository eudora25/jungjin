<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalNursingGrade extends Model
{
    protected $fillable = [
        'hospital_id', 'insurance_type_code', 'insurance_type_name', 'nursing_grade',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
