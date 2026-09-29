<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Notice of Availability of Fund (Through ACP implementation preparation). */
class ProjectNafa extends Model
{
    protected $fillable = [
        'project_id',
        'nafa_date',
        'release_date',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'nafa_date' => 'date',
            'release_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectNafaAttachment::class)->orderBy('id');
    }
}
