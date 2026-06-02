<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalSpecialty extends Model
{
    protected $fillable = [
        'hospital_id', 'dept_code', 'dept_name', 'specialist_count', 'selective_doctor_count',
    ];

    protected $casts = [
        'specialist_count' => 'integer',
        'selective_doctor_count' => 'integer',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
