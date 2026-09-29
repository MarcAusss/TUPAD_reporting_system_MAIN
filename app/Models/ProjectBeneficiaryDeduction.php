<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBeneficiaryDeduction extends Model
{
    protected $fillable = [
        'project_id',
        'project_beneficiary_address_id',
        'beneficiaries_deducted',
        'female_deducted',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'beneficiaries_deducted' => 'integer',
            'female_deducted' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ProjectBeneficiaryAddress::class, 'project_beneficiary_address_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
