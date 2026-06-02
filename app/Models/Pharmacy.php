<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessNumberHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pharmacy extends Model
{
    use HasBusinessNumberHistory;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'pharmacy_code',
        'pharmacy_name',
        'business_registration_number',
        'representative_name',
        'postcode',
        'address',
        'landline_phone',
        'mobile_phone',
        'contact_person_name',
        'contact_phone',
        'email',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    /** 사업자등록번호는 숫자만 저장 (하이픈 무관 검색·표시). import/factory 등 모든 쓰기 경로에서 정규화 */
    protected function businessRegistrationNumber(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : (preg_replace('/\D/', '', (string) $value) ?: null),
        );
    }

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
