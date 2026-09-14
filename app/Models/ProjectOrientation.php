<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectOrientation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'orientation_date',
        'beneficiaries_oriented',
        'venue',
        'oriented_by',
        'alkansssya_conducted',
        'yakap_conducted',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'orientation_date' => 'date',
            'beneficiaries_oriented' => 'integer',
            'alkansssya_conducted' => 'boolean',
            'yakap_conducted' => 'boolean',
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