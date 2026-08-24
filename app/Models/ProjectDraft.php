<?php

namespace App\Models;

use App\Enums\ImplementationMode;
use App\Enums\ProjectDraftStatus;
use App\Enums\ProjectTerm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'encoded_by',
        'assigned_tc_id',
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

        'tc_review_remarks',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',

        'province_id',
        'municipality_id',
        'barangay_id',

        'confirmed_project_id',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',

            'implementation_mode' => ImplementationMode::class,
            'term' => ProjectTerm::class,
            'status' => ProjectDraftStatus::class,

            'wage_rate' => 'decimal:2',
            'wages_total' => 'decimal:2',

            'ppe_total' => 'decimal:2',

            'insurance_rate' => 'decimal:2',
            'insurance_total' => 'decimal:2',

            'total_project_cost' => 'decimal:2',

            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'encoded_by'
        );
    }

    public function assignedTc(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_tc_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
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
        return $this->hasMany(
            ProjectDraftPpeItem::class
        );
    }

    public function confirmedProject(): BelongsTo
    {
        return $this->belongsTo(
            Project::class,
            'confirmed_project_id'
        );
    }

    public function canBeEdited(): bool
    {
        return in_array(
            $this->status,
            [
                ProjectDraftStatus::DRAFT,
                ProjectDraftStatus::RETURNED_FOR_CORRECTION,
            ],
            true
        );
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }

    public function isPendingReview(): bool
    {
        return $this->status
            === ProjectDraftStatus::PENDING_TC_REVIEW;
    }

    public function isConfirmed(): bool
    {
        return $this->status
            === ProjectDraftStatus::CONFIRMED;
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
}