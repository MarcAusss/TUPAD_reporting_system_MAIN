<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'governor_updated_at' => 'datetime',
        ];
    }

    public function governorUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'governor_updated_by');
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(
            Municipality::class
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(
            Project::class
        );
    }


    public function assignedCoordinators(): HasMany
    {
        return $this->hasMany(
            User::class,
            'assigned_province_id'
        );
    }
}
