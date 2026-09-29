<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNafaAttachment extends Model
{
    protected $fillable = [
        'project_nafa_id',
        'original_name',
        'attachment_path',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function nafa(): BelongsTo
    {
        return $this->belongsTo(ProjectNafa::class, 'project_nafa_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
