<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'municipality_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(
            Municipality::class
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(
            Project::class
        );
    }

    public function projectDrafts(): HasMany
    {
        return $this->hasMany(
            ProjectDraft::class
        );
    }

    public function getFullLocationAttribute(): string
    {
        return implode(', ', [
            $this->name,
            $this->municipality->name,
            $this->municipality->province->name,
        ]);
    }
}