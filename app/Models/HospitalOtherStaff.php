<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalOtherStaff extends Model
{
    // 자동 추론(hospital_other_staves 등) 회피 위해 명시
    protected $table = 'hospital_other_staff';

    protected $fillable = ['hospital_id', 'staff_code', 'staff_name', 'staff_count'];

    protected $casts = ['staff_count' => 'integer'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
