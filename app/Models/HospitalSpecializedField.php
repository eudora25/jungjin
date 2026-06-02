<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalSpecializedField extends Model
{
    protected $fillable = ['hospital_id', 'field_code', 'field_name'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
