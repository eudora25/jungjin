<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 공유 마스터(약국·병의원) 변경요청. (GAP-10 MT-8)
 * tenant-scoped: pharma 는 자사 요청만, platform 은 전역(미진입 시) 조회.
 */
class MasterChangeRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const TARGET_PHARMACY = 'pharmacy';

    public const TARGET_HOSPITAL = 'hospital';

    public const TYPE_CREATE = 'create';

    public const TYPE_UPDATE = 'update';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'requested_by',
        'target_type',
        'target_id',
        'request_type',
        'payload',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'applied_target_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** 대상 모델 클래스 */
    public function targetModelClass(): string
    {
        return $this->target_type === self::TARGET_HOSPITAL ? Hospital::class : Pharmacy::class;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
