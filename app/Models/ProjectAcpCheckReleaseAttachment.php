<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAcpCheckReleaseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_acp_check_release_id', 'original_name', 'attachment_path', 'mime_type', 'file_size',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(ProjectAcpCheckRelease::class, 'project_acp_check_release_id');
    }
}
