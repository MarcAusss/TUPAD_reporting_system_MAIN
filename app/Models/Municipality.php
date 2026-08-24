<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'code',
        'name',
        'district',
        'income_class',
        'is_city',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_city' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(
            Province::class
        );
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(
            Barangay::class
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

    public function getLocationLabelAttribute(): string
    {
        return "{$this->name}, {$this->province->name}";
    }
}