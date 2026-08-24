<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdlAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'adl_id',
        'fund_sponsor',
        'partner',
        'local_chief_executive_partylist',
        'location',
        'province',
        'district',
        'municipality',
        'amount',
        'grant_amount',
        'admin_cost_amount',
        'total_amount',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'grant_amount' => 'decimal:2',
            'admin_cost_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function adl(): BelongsTo
    {
        return $this->belongsTo(Adl::class);
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
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function getTotalProjectCostAttribute(): float
    {
        return (float) $this->projects()
            ->sum('total_project_cost');
    }

    public function getRemainingProjectBudgetAttribute(): float
    {
        return (float) $this->amount
            - $this->total_project_cost;
    }
}