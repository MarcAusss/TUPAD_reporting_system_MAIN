<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectAcpCheckRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'check_number', 'check_date', 'amount', 'released_date',
        'released_to', 'remarks', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['check_date' => 'date', 'released_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function attachments(): HasMany { return $this->hasMany(ProjectAcpCheckReleaseAttachment::class); }
}
