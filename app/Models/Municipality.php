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
            'income_class_updated_at' => 'datetime',
            'mayor_updated_at' => 'datetime',
        ];
    }

    public function mayorUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mayor_updated_by');
    }

    public function incomeClassUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'income_class_updated_by');
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


    public function getLocationLabelAttribute(): string
    {
        return "{$this->name}, {$this->province->name}";
    }
}