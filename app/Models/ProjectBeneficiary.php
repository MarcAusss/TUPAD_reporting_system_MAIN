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
        'remarks',
        'encoded_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
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
}