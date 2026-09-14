<?php

namespace App\Models;

use App\Enums\BeneficiaryReplacementRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBeneficiaryReplacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'reason',
        'performed_by',
        'performed_at',

        'insurance_beneficiaries_changed',
        'insurance_beneficiaries_before',
        'insurance_beneficiaries_after',

        'total_project_cost_changed',
        'total_project_cost_before',
        'total_project_cost_after',

        'beneficiary_address_changed',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',

            'insurance_beneficiaries_changed' => 'boolean',
            'insurance_beneficiaries_before' => 'integer',
            'insurance_beneficiaries_after' => 'integer',

            'total_project_cost_changed' => 'boolean',
            'total_project_cost_before' => 'decimal:2',
            'total_project_cost_after' => 'decimal:2',

            'beneficiary_address_changed' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by'
        );
    }

    public function members(): HasMany
    {
        return $this->hasMany(
            ProjectBeneficiaryReplacementMember::class,
            'replacement_id'
        );
    }

    public function removedMembers(): HasMany
    {
        return $this->members()
            ->where('role', BeneficiaryReplacementRole::REMOVED);
    }

    public function addedMembers(): HasMany
    {
        return $this->members()
            ->where('role', BeneficiaryReplacementRole::ADDED);
    }

    public function hasAnyDetailChange(): bool
    {
        return $this->insurance_beneficiaries_changed
            || $this->total_project_cost_changed
            || $this->beneficiary_address_changed;
    }
}
