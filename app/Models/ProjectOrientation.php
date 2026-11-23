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

    /**
     * AlkanSSSya and the YAKAP Program are mandatory parts of every TUPAD
     * beneficiary orientation, so every orientation record covers both.
     */
    protected static function booted(): void
    {
        static::saving(function (ProjectOrientation $orientation): void {
            $orientation->alkansssya_conducted = true;
            $orientation->yakap_conducted = true;
        });
    }

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