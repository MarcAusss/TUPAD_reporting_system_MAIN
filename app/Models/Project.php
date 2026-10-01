<?php

namespace App\Models;

use App\Enums\ImplementationMode;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class Project extends Model
{
    use HasFactory;

    private ?int $statusTransitionActorId = null;

    private ?string $statusTransitionRemarks = null;

    private bool $statusTransitionContextSet = false;

    protected $fillable = [
        'adl_allocation_id',
        'date_received',
        'project_title',
        'nature_of_work',

        'fund_sponsor',
        'partner',
        'program',

        'project_series',
        'project_series_remarks',
        'tevs_date_verified',
        'tevs_remarks',

        'province',
        'district',
        'municipality',
        'barangay',
        'income_class',

        'implementation_mode',
        'number_of_days',
        'term',
        'intervention_focus',

        'beneficiaries_total',
        'beneficiaries_female',

        'wage_rate',
        'wages_total',

        'ppe_total',

        'insurance_rate',
        'insurance_beneficiaries',
        'insurance_total',

        'total_project_cost',

        'status',
        'remarks',

        'obligations_completed_at',
        'obligations_completed_by',
        'beneficiary_deductions_recorded_at',
        'beneficiary_deductions_recorded_by',

        'province_id',
        'municipality_id',
        'barangay_id',

        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'tevs_date_verified' => 'date',

            'implementation_mode' => ImplementationMode::class,
            'term' => ProjectTerm::class,
            'intervention_focus' => ProjectInterventionFocus::class,
            'status' => ProjectStatus::class,

            'wage_rate' => 'decimal:2',
            'wages_total' => 'decimal:2',
            'ppe_total' => 'decimal:2',

            'insurance_rate' => 'decimal:2',
            'insurance_beneficiaries' => 'integer',
            'insurance_total' => 'decimal:2',

            'total_project_cost' => 'decimal:2',

            'obligations_completed_at' => 'datetime',
            'beneficiary_deductions_recorded_at' => 'datetime',
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            AdlAllocation::class,
            'adl_allocation_id'
        );
    }

    public function ppeItems(): HasMany
    {
        return $this->hasMany(ProjectPpeItem::class);
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

    public function getAdlAttribute(): ?Adl
    {
        return $this->allocation?->adl;
    }


    public function evaluations(): HasMany
    {
        return $this->hasMany(ProjectEvaluation::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(ProjectApproval::class);
    }
    public function insuranceEnrollment(): HasOne
    {
        return $this->hasOne(
            ProjectInsuranceEnrollment::class
        );
    }

    public function ppeDeliveries(): HasMany
    {
        return $this->hasMany(
            ProjectPpeDelivery::class
        )->orderBy('delivery_receipt_date')
            ->orderBy('id');
    }

    public function noticeToProceed(): HasOne
    {
        return $this->hasOne(
            ProjectNoticeToProceed::class
        );
    }

    public function orientation(): HasOne
    {
        return $this->hasOne(
            ProjectOrientation::class
        );
    }

    public function implementation(): HasOne
    {
        return $this->hasOne(
            ProjectImplementation::class
        );
    }

    public function preImplementationRequirementsComplete(): bool
    {
        return $this->insuranceEnrollment()->exists()
            && $this->ppeDeliveries()->exists()
            && $this->noticeToProceed()->exists();
    }

    public function implementationPreparationComplete(): bool
    {
        return $this->preImplementationRequirementsComplete()
            && $this->orientation()->exists()
            && $this->implementation()->exists();
    }
    public function postDocuments(): HasMany
    {
        return $this->hasMany(
            ProjectPostDocument::class
        );
    }

    public function obligation(): HasOne
    {
        return $this->hasOne(
            ProjectObligation::class
        )->oldestOfMany('tranche_number');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(ProjectObligation::class)
            ->orderBy('tranche_number');
    }

    /** Through ACP: Notice of Availability of Fund. */
    public function nafa(): HasOne
    {
        return $this->hasOne(ProjectNafa::class);
    }

    public function beneficiaryDeductions(): HasMany
    {
        return $this->hasMany(ProjectBeneficiaryDeduction::class);
    }

    public function beneficiaryDeductionsRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_deductions_recorded_by');
    }

    public function editRequests(): HasMany
    {
        return $this->hasMany(ProjectEditRequest::class)->latest();
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(ProjectEditLog::class)->latest();
    }

    public function obligationsCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'obligations_completed_by');
    }

    public function payout(): HasOne
    {
        return $this->hasOne(
            ProjectPayout::class
        );
    }

    public function acpPayment(): HasOne
    {
        return $this->hasOne(ProjectAcpPayment::class);
    }

    public function acpCheckRelease(): HasOne
    {
        return $this->hasOne(ProjectAcpCheckRelease::class);
    }

    public function acpLiquidations(): HasMany
    {
        return $this->hasMany(ProjectAcpLiquidation::class)
            ->orderBy('liquidation_date')
            ->orderBy('id');
    }

    public function postDocumentsComplete(): bool
    {
        return $this->postDocuments()
            ->whereNotNull('date_forwarded_to_imsd')
            ->exists();
    }
    public function statusHistory(): HasMany
    {
        return $this->hasMany(
            ProjectStatusHistory::class
        );
    }

    /**
     * Adds status_entered_at: when the project last moved into its current status.
     */
    public function scopeWithStatusEnteredAt(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        return $query->addSelect([
            'status_entered_at' => ProjectStatusHistory::query()
                ->selectRaw('MAX(changed_at)')
                ->whereColumn('project_status_histories.project_id', 'projects.id')
                ->whereColumn('project_status_histories.to_status', 'projects.status'),
        ]);
    }

    /**
     * Proposed amount: the Total Project Cost encoded at project creation.
     */
    public function proposedAmount(): float
    {
        return (float) $this->total_project_cost;
    }

    /**
     * Actual amount, once it is final:
     * - Direct Administration: total of all obligation tranches after the Focal
     *   completes them (this already excludes deducted beneficiaries);
     * - Through ACP: the recorded ACP payment amount.
     * Null while it is not yet final.
     */
    public function actualAmount(): ?float
    {
        if ($this->implementation_mode === ImplementationMode::THROUGH_ACP) {
            $payment = $this->relationLoaded('acpPayment') ? $this->acpPayment : $this->acpPayment()->first();

            return $payment !== null ? (float) $payment->amount : null;
        }

        if ($this->obligations_completed_at === null) {
            return null;
        }

        $obligations = $this->relationLoaded('obligations') ? $this->obligations : $this->obligations()->get();

        // Sum in cents to avoid floating-point drift across tranches.
        $cents = $obligations->sum(fn ($obligation): int => (int) round(((float) $obligation->amount) * 100));

        return $cents / 100;
    }

    /**
     * The amount reports use for this project: the actual amount when final,
     * otherwise the proposed amount.
     */
    public function reportAmount(): float
    {
        return $this->actualAmount() ?? $this->proposedAmount();
    }

    /**
     * Actual beneficiaries [total, female], once final: the beneficiaries on all
     * obligation tranches after the Focal completes them (already net of the
     * beneficiaries not included). Through ACP has no tranches, so it keeps the
     * approved counts. Null while not yet final.
     *
     * @return array{0:int,1:int}|null
     */
    public function actualBeneficiaryCounts(): ?array
    {
        if (
            $this->implementation_mode === ImplementationMode::THROUGH_ACP
            || $this->obligations_completed_at === null
        ) {
            return null;
        }

        $obligations = $this->relationLoaded('obligations') ? $this->obligations : $this->obligations()->get();

        return [
            (int) $obligations->sum('beneficiaries_total'),
            (int) $obligations->sum('beneficiaries_female'),
        ];
    }

    public function actualBeneficiaries(): ?int
    {
        return $this->actualBeneficiaryCounts()[0] ?? null;
    }

    public function actualFemaleBeneficiaries(): ?int
    {
        return $this->actualBeneficiaryCounts()[1] ?? null;
    }

    /** Beneficiaries reports use: actual when final, otherwise approved. */
    public function reportBeneficiaries(): int
    {
        return $this->actualBeneficiaries() ?? (int) $this->beneficiaries_total;
    }

    /** Female beneficiaries reports use: actual when final, otherwise approved. */
    public function reportFemaleBeneficiaries(): int
    {
        return $this->actualFemaleBeneficiaries() ?? (int) $this->beneficiaries_female;
    }

    /**
     * Projects that have stayed in their current (non-completed) status for at least $days days.
     */
    public function scopeAgedInStatus(\Illuminate\Database\Eloquent\Builder $query, int $days): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('projects.status', '!=', ProjectStatus::COMPLETED->value)
            ->whereRaw(
                'COALESCE((SELECT MAX(h.changed_at) FROM project_status_histories h WHERE h.project_id = projects.id AND h.to_status = projects.status), projects.updated_at) <= ?',
                [now()->subDays($days)->endOfDay()->toDateTimeString()],
            );
    }

    /**
     * Quick filters shared by the registry and the queue pages (?aging=14, ?period=this_month, ?mine=1).
     */
    public function scopeApplyQuickFilters(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $aging = $request->integer('aging');

        return $query
            ->when($aging > 0, fn ($builder) => $builder->agedInStatus(min($aging, 3650)))
            ->when($request->query('period') === 'this_month', fn ($builder) => $builder->whereBetween('projects.date_received', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]))
            ->when($request->boolean('mine') && $request->user(), fn ($builder) => $builder->where('projects.created_by', $request->user()->id));
    }

    /**
     * When the project entered its current workflow status (falls back to
     * the last update for projects without a matching history row).
     */
    public function statusEnteredAt(): ?\Illuminate\Support\Carbon
    {
        if (array_key_exists('status_entered_at', $this->attributes)) {
            $value = $this->attributes['status_entered_at'];
        } elseif ($this->relationLoaded('statusHistory')) {
            $value = $this->statusHistory
                ->filter(fn (ProjectStatusHistory $history) => $history->to_status === $this->status)
                ->max('changed_at');
        } else {
            $value = $this->statusHistory()->where('to_status', $this->status?->value)->max('changed_at');
        }

        $value ??= $this->updated_at ?? $this->created_at;

        return $value ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    public function daysInCurrentStatus(): ?int
    {
        $enteredAt = $this->statusEnteredAt();

        return $enteredAt ? (int) $enteredAt->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    public function setStatusTransitionContext(
        ?int $actorId,
        ?string $remarks,
    ): self {
        $this->statusTransitionActorId = $actorId;
        $this->statusTransitionRemarks = $remarks;
        $this->statusTransitionContextSet = true;

        return $this;
    }

    public function clearStatusTransitionContext(): self
    {
        $this->statusTransitionActorId = null;
        $this->statusTransitionRemarks = null;
        $this->statusTransitionContextSet = false;

        return $this;
    }

    public function statusTransitionActorId(): ?int
    {
        return $this->statusTransitionActorId;
    }

    public function hasStatusTransitionContext(): bool
    {
        return $this->statusTransitionContextSet;
    }

    public function statusTransitionRemarks(): ?string
    {
        return $this->statusTransitionRemarks;
    }

    public function projectLocations(): HasMany
    {
        return $this->hasMany(ProjectLocation::class)
            ->orderBy('sort_order');
    }

    public function beneficiarySectors(): HasMany
    {
        return $this->hasMany(ProjectBeneficiarySector::class)
            ->orderBy('sector_group')
            ->orderBy('sector_key');
    }

    public function beneficiaryAddresses(): HasMany
    {
        return $this->hasMany(ProjectBeneficiaryAddress::class)
            ->orderBy('municipality_id')
            ->orderBy('barangay_id');
    }

    public function barangayPpeProfiles(): HasMany
    {
        return $this->hasMany(ProjectBarangayPpeProfile::class);
    }

    public function barangayPpeItemCounts(): HasMany
    {
        return $this->hasMany(ProjectBarangayPpeItemCount::class);
    }

    public function laborMarketReferrals(): HasMany
    {
        return $this->hasMany(ProjectLaborMarketReferral::class)
            ->orderByDesc('reporting_month')
            ->orderBy('program');
    }

    public function provinceReference(): BelongsTo
    {
        return $this->belongsTo(
            Province::class,
            'province_id'
        );
    }

    public function municipalityReference(): BelongsTo
    {
        return $this->belongsTo(
            Municipality::class,
            'municipality_id'
        );
    }

    public function barangayReference(): BelongsTo
    {
        return $this->belongsTo(
            Barangay::class,
            'barangay_id'
        );
    }

    public function getFullLocationAttribute(): string
    {
        $this->loadMissing([
            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',
        ]);

        $primaryLocation = $this->projectLocations->first();

        if ($primaryLocation) {
            $primaryBarangay = $primaryLocation->barangays
                ->sortBy('id')
                ->first();

            return collect([
                $primaryBarangay?->name,
                $primaryLocation->municipality?->name,
                $primaryLocation->province?->name,
            ])->filter()->implode(', ');
        }

        if (
            $this->barangayReference
            && $this->municipalityReference
            && $this->provinceReference
        ) {
            return implode(', ', [
                $this->barangayReference->name,
                $this->municipalityReference->name,
                $this->provinceReference->name,
            ]);
        }

        return implode(
            ', ',
            array_filter([
                $this->barangay,
                $this->municipality,
                $this->province,
            ])
        );
    }

    public function getPaymentLocationSummaryAttribute(): string
    {
        $this->loadMissing([
            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',
        ]);

        if ($this->projectLocations->isEmpty()) {
            return $this->full_location;
        }

        return $this->projectLocations
            ->map(function (ProjectLocation $location): string {
                $barangays = $location->barangays
                    ->pluck('name')
                    ->filter()
                    ->implode(', ');

                return collect([
                    $barangays,
                    $location->municipality?->name,
                    $location->district,
                    $location->province?->name,
                ])->filter()->implode(' / ');
            })
            ->filter()
            ->implode('; ');
    }

    public function monitoringDetail(): HasOne
    {
        return $this->hasOne(ProjectMonitoringDetail::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(
            ProjectBeneficiary::class
        );
    }

    public function beneficiaryReplacements(): HasMany
    {
        return $this->hasMany(
            ProjectBeneficiaryReplacement::class
        )->orderByDesc('performed_at');
    }

    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(
            ProjectInsuranceClaim::class
        )->orderByDesc('incident_date')->orderByDesc('id');
    }

    public function beneficiaryRegistryCount(): int
    {
        return $this->beneficiaries()
            ->count();
    }

    public function beneficiaryRegistryFemaleCount(): int
    {
        return $this->beneficiaries()
            ->where('sex', 'female')
            ->count();
    }

    public function beneficiaryRegistryComplete(): bool
    {
        return $this->beneficiaryRegistryCount()
            === (int) $this->beneficiaries_total;
    }
}
