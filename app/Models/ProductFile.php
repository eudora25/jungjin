<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductFile extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const TYPE_LICENSE = 'license';
    public const TYPE_SAFETY = 'safety';
    public const TYPE_CATALOG = 'catalog';
    public const TYPE_ETC = 'etc';

    public const TYPES = [
        self::TYPE_LICENSE,
        self::TYPE_SAFETY,
        self::TYPE_CATALOG,
        self::TYPE_ETC,
    ];

    protected $fillable = [
        'product_id',
        'file_type',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['product_id', 'file_type', 'original_name', 'stored_name', 'size', 'mime_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('product.file')
            ->setDescriptionForEvent(fn (string $event) => "product.file.{$event}");
    }
}
