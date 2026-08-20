<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'payout_date',
        'payout_mode',
        'venue',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'payout_date' => 'date',
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