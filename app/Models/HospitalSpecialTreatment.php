<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalSpecialTreatment extends Model
{
    protected $fillable = ['hospital_id', 'search_code', 'search_name'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
