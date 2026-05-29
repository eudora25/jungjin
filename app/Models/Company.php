<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_name',
        'business_registration_number',
        'representative_name',
        'company_group',
        'partner_type',
        'default_commission_grade',
        'postcode',
        'business_address',
        'contact_person_name',
        'landline_phone',
        'mobile_phone',
        'mobile_phone_2',
        'email',
        'receive_email',
        'assigned_pharmacist_contact',
        'remarks',
        'status',
        'approval_status',
        'approved_at',
        'approved_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function productOverrides(): HasMany
    {
        return $this->hasMany(CompanyProductOverride::class)
            ->orderByDesc('effective_from');
    }

    public function salesAssignments(): HasMany
    {
        return $this->hasMany(CompanySalesAssignment::class);
    }

    public function salesUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_sales_assignments')
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', 'pending');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
