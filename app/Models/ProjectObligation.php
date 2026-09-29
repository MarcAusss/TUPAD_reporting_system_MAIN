<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectObligation extends Model
{
    use HasFactory;

    /**
     * SQL condition: the tranche's disbursements cover its full obligation.
     */
    public const FULLY_DISBURSED_SQL = 'project_obligations.amount <= (
        select coalesce(sum(project_disbursements.amount), 0)
        from project_disbursements
        where project_disbursements.project_obligation_id = project_obligations.id
    )';

    protected $fillable = [
        'project_id',
        'tranche_number',

        'adl_number',
        'fund_sponsor',
        'partner',
        'project_location',
        'term',

        'beneficiaries_total',
        'beneficiaries_female',

        'wages_amount',
        'insurance_amount',
        'ppe_amount',
        'amount',

        'obligation_date',
        'month',
        'payee',

        'remarks',

        'release_mode',
        'release_date',
        'release_venue',
        'release_remarks',
        'released_by',
        'released_at',

        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'tranche_number' => 'integer',
            'beneficiaries_total' => 'integer',
            'beneficiaries_female' => 'integer',
            'wages_amount' => 'decimal:2',
            'insurance_amount' => 'decimal:2',
            'ppe_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'obligation_date' => 'date',
            'release_date' => 'date',
            'released_at' => 'datetime',
        ];
    }

    public function isReleased(): bool
    {
        return $this->release_date !== null;
    }

    /**
     * Fully disbursed tranches still waiting for the TUPAD Coordinator's
     * Release of Assistance.
     */
    public function scopeAwaitingRelease(Builder $query): Builder
    {
        return $query
            ->whereNull('project_obligations.release_date')
            ->whereRaw(self::FULLY_DISBURSED_SQL);
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

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(ProjectDisbursement::class)
            ->orderBy('date_disbursed')
            ->orderBy('id');
    }
}
