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

    public function ppeDelivery(): HasOne
    {
        return $this->hasOne(
            ProjectPpeDelivery::class
        );
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
            && $this->ppeDelivery()->exists()
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
