<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use BelongsToAccount;

    public const KIND_BUSINESS_EXPENSE = 'business_expense';

    public const KIND_TENANT_DOCUMENT = 'tenant_document';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'kind',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
