<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectEditLog extends Model
{
    protected $fillable = [
        'project_id',
        'section',
        'record_id',
        'changes',
        'edited_by',
        'approved_by',
        'edit_request_id',
    ];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'changes' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
