<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 정산 지급 증빙 파일 (GAP-5).
 * 이체확인서 / 지급내역 캡처 등 (PDF/이미지).
 */
class SettlementPaymentFile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'settlement_id',
        'original_name',
        'stored_name',
        'path',
        'size',
        'mime_type',
        'extension',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
