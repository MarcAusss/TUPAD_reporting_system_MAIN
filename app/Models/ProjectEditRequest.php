<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEditRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';
    public const USED = 'used';

    protected $fillable = [
        'project_id',
        'section',
        'record_id',
        'reason',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'decided_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function scopeForTarget(Builder $query, string $section, int $recordId): Builder
    {
        return $query->where('section', $section)->where('record_id', $recordId);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
