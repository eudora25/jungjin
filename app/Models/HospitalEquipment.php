<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalEquipment extends Model
{
    // "equipment" 는 불가산 명사라 자동 추론이 hospital_equipment 가 되어 명시
    protected $table = 'hospital_equipments';

    protected $fillable = [
        'hospital_id', 'equipment_code', 'equipment_name', 'quantity',
    ];

    protected $casts = ['quantity' => 'integer'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
