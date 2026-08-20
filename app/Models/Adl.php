<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adl extends Model
{
    use HasFactory;

    protected $fillable = [
        'adl_number',
        'grants',
        'admin_cost',
        'total',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'grants' => 'decimal:2',
            'admin_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function realignments(): HasMany
    {
        return $this->hasMany(AdlRealignment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AdlAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function getTotalRealignmentAttribute(): float
    {
        return (float) $this->realignments()->sum('amount');
    }

    public function getAdjustedGrantsAttribute(): float
    {
        return (float) $this->grants
            + $this->total_realignment;
    }

    public function getTotalAllocatedAttribute(): float
    {
        return (float) $this->allocations()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return $this->adjusted_grants
            - $this->total_allocated;
    }
}