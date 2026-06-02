<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalTransport extends Model
{
    protected $fillable = [
        'hospital_id', 'transport_type', 'route_no', 'stop_name', 'direction', 'distance', 'remarks',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
