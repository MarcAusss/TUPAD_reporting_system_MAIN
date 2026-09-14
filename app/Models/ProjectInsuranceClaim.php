<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectInsuranceClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'incident_description',
        'incident_date',
        'incident_time',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'incident_time' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by'
        );
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(
            ProjectInsuranceClaimBeneficiary::class,
            'claim_id'
        );
    }
}
