<?php

namespace App\Models;

use App\Enums\ImplementationMode;
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

    protected $fillable = [
        'adl_allocation_id',
        'date_received',
        'project_title',
        'nature_of_work',

        'province',
        'district',
        'municipality',
        'barangay',
        'income_class',

        'implementation_mode',
        'number_of_days',
        'term',

        'beneficiaries_total',
        'beneficiaries_female',

        'wage_rate',
        'wages_total',

        'ppe_total',

        'insurance_rate',
        'insurance_total',

        'total_project_cost',

        'status',
        'remarks',

        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',

            'implementation_mode' => ImplementationMode::class,
            'term' => ProjectTerm::class,
            'status' => ProjectStatus::class,

            'wage_rate' => 'decimal:2',
            'wages_total' => 'decimal:2',
            'ppe_total' => 'decimal:2',

            'insurance_rate' => 'decimal:2',
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

    public function getFundSponsorAttribute(): ?string
    {
        return $this->allocation?->fund_sponsor;
    }

    public function getPartnerAttribute(): ?string
    {
        return $this->allocation?->partner;
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

    public function implementationPreparationComplete(): bool
    {
        return $this->insuranceEnrollment()->exists()
            && $this->ppeDelivery()->exists()
            && $this->noticeToProceed()->exists()
            && $this->orientation()->exists()
            && $this->implementation()->exists();
    }
}