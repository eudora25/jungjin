<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalMoisCursor extends Model
{
    protected $fillable = [
        'api_id', 'last_synced_at', 'last_sync_id',
    ];

    public function lastSync(): BelongsTo
    {
        return $this->belongsTo(HospitalMoisSync::class, 'last_sync_id');
    }
}
