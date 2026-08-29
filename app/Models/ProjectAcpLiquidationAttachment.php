<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAcpLiquidationAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_acp_liquidation_id',
        'original_name',
        'attachment_path',
        'mime_type',
        'file_size',
    ];

    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(
            ProjectAcpLiquidation::class,
            'project_acp_liquidation_id'
        );
    }
}
