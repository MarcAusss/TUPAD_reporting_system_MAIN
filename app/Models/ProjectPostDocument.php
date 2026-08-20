<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPostDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date_received',
        'document_type',
        'attachment_path',
        'remarks',
        'date_forwarded_to_imsd',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'date_forwarded_to_imsd' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}