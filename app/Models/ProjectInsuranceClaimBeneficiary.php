<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInsuranceClaimBeneficiary extends Model
{
    protected $fillable = [
        'claim_id',
        'beneficiary_id',
        'full_name',
        'address',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(
            ProjectInsuranceClaim::class,
            'claim_id'
        );
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(ProjectBeneficiary::class);
    }
}
