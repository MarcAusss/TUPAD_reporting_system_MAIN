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
        'project_post_document_id',
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
            'project_post_document_id' => 'integer',
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

    /**
     * Beneficiaries [total, female] the tranches paid. Long-term tranches
     * created from the TUPAD Coordinator's tranche submissions pay the same
     * beneficiaries over different coverage dates, so the headcount is the
     * largest tranche; other tranches pay different beneficiaries and add up.
     *
     * @param  iterable<self>  $obligations
     * @return array{0:int,1:int}
     */
    public static function paidBeneficiaryCounts(iterable $obligations): array
    {
        $obligations = collect($obligations);

        if ($obligations->contains(fn (self $obligation): bool => $obligation->isLongTermTranche())) {
            return [
                (int) $obligations->max('beneficiaries_total'),
                (int) $obligations->max('beneficiaries_female'),
            ];
        }

        return [
            (int) $obligations->sum('beneficiaries_total'),
            (int) $obligations->sum('beneficiaries_female'),
        ];
    }

    /**
     * SQL aggregate over a project's project_obligations rows matching
     * paidBeneficiaryCounts(), for the "total" or "female" column.
     */
    public static function paidBeneficiariesAggregateSql(string $column): string
    {
        $column = $column === 'female' ? 'beneficiaries_female' : 'beneficiaries_total';

        return "coalesce(case when count(project_obligations.project_post_document_id) > 0
            then max(project_obligations.{$column})
            else sum(project_obligations.{$column}) end, 0)";
    }

    /** Created from a long-term tranche submission (same beneficiaries, different dates). */
    public function isLongTermTranche(): bool
    {
        return $this->project_post_document_id !== null;
    }

    public function isReleased(): bool
    {
        return $this->release_date !== null;
    }

    public function postDocument(): BelongsTo
    {
        return $this->belongsTo(ProjectPostDocument::class, 'project_post_document_id');
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
