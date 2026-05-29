<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hospital extends Model
{
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
        'hospital_name',
        'business_registration_number',
        'hospital_type',
        'specialty',
        'representative_name',
        'postcode',
        'address',
        'phone',
        'contact_person_name',
        'contact_phone',
        'email',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
