<?php

namespace App\Models;

use App\Enums\BeneficiaryReplacementRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'sex',
        'birth_date',
        'contact_number',
        'is_pwd',
        'is_rebel_returnee',
        'grant_amount',
        'remarks',
        'encoded_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_pwd' => 'boolean',
            'is_rebel_returnee' => 'boolean',
            'grant_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'encoded_by'
        );
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            implode(' ', array_filter([
                $this->first_name,
                $this->middle_name,
                $this->last_name,
                $this->suffix,
            ]))
        );
    }

    public function isFemale(): bool
    {
        return $this->sex === 'female';
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    public function isYouth(): bool
    {
        $age = $this->age();
        return $age !== null && $age >= 15 && $age <= 30;
    }

    public function isSeniorCitizen(): bool
    {
        $age = $this->age();
        return $age !== null && $age >= 60;
    }

    public function replacementMemberships(): HasMany
    {
        return $this->hasMany(
            ProjectBeneficiaryReplacementMember::class,
            'beneficiary_id'
        );
    }

    /**
     * Whether this beneficiary left the project through a recorded
     * replacement transaction (the row itself is never deleted).
     */
    public function isReplaced(): bool
    {
        return $this->replacementMemberships()
            ->where('role', BeneficiaryReplacementRole::REMOVED)
            ->exists();
    }

    /**
     * Whether this beneficiary joined the project as a replacement,
     * rather than being part of the originally declared roster.
     */
    public function isReplacement(): bool
    {
        return $this->replacementMemberships()
            ->where('role', BeneficiaryReplacementRole::ADDED)
            ->exists();
    }
}