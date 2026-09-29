<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEvaluationAttachment extends Model
{
    /** Uploaded by TSSD with a For Compliance evaluation (findings / required documents). */
    public const KIND_EVALUATION = 'evaluation';

    /** Uploaded by the TUPAD Coordinator with the compliance submission. */
    public const KIND_COMPLIANCE = 'compliance';

    protected $fillable = [
        'project_evaluation_id',
        'kind',
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

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ProjectEvaluation::class, 'project_evaluation_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
