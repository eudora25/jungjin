<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessNumberHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hospital extends Model
{
    use HasBusinessNumberHistory;
    use HasFactory;
    use SoftDeletes;

    public const TYPES = [
        'general_hospital',
        'hospital',
        'clinic',
        'dental',
        'oriental',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'hospital_code',
        'ykiho',
        'hospital_name',
        'business_registration_number',
        'hospital_type',
        'clazz_code',
        'sido_code',
        'sigungu_code',
        'eupmyeondong',
        'specialty',
        'representative_name',
        'postcode',
        'address',
        'phone',
        'opened_on',
        'closed_on',
        'suspend_begin_on',
        'suspend_end_on',
        'doctor_count',
        'bed_count',
        'inpatient_room_count',
        'total_area',
        'latitude',
        'longitude',
        'homepage',
        'license_authority_code',
        'source_synced_at',
        'contact_person_name',
        'contact_phone',
        'email',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opened_on' => 'date',
        'closed_on' => 'date',
        'suspend_begin_on' => 'date',
        'suspend_end_on' => 'date',
        'doctor_count' => 'integer',
        'bed_count' => 'integer',
        'inpatient_room_count' => 'integer',
        'total_area' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'source_synced_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(HospitalSpecialty::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(HospitalEquipment::class);
    }

    public function facility(): HasOne
    {
        return $this->hasOne(HospitalFacility::class);
    }

    public function hours(): HasOne
    {
        return $this->hasOne(HospitalHour::class);
    }

    public function transports(): HasMany
    {
        return $this->hasMany(HospitalTransport::class);
    }

    public function nursingGrades(): HasMany
    {
        return $this->hasMany(HospitalNursingGrade::class);
    }

    public function mealSurcharges(): HasMany
    {
        return $this->hasMany(HospitalMealSurcharge::class);
    }

    public function specialTreatments(): HasMany
    {
        return $this->hasMany(HospitalSpecialTreatment::class);
    }

    public function specializedFields(): HasMany
    {
        return $this->hasMany(HospitalSpecializedField::class);
    }

    public function otherStaff(): HasMany
    {
        return $this->hasMany(HospitalOtherStaff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
