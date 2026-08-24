<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}