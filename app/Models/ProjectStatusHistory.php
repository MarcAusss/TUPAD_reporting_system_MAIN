<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusHistory extends Model
{
    protected $fillable = [
        'project_id',
        'from_status',
        'to_status',
        'changed_by',
        'remarks',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ProjectStatus::class,
            'to_status' => ProjectStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by'
        );
    }
}