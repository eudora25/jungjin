<?php

namespace App\Models;

use App\Services\Clients\HiraDetailImportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalPublicDataImport extends Model
{
    public const KIND_INSTITUTION = 'institution';

    /** 업로드 가능한 적재 유형 — institution(B-1) + 상세 10종 */
    public const KINDS = [self::KIND_INSTITUTION, ...HiraDetailImportService::TYPES];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'created_by', 'kind', 'original_filename', 'stored_path',
        'status', 'report', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'report' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
