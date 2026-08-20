<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNoticeToProceed extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date_issued',
        'date_released',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'date_released' => 'date',
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