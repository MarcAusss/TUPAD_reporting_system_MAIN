<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Insurance and PPE payable at Payment of Wages, after deducting the
 * beneficiaries not included in the obligation tranches.
 */
class ProjectPaymentInsurancePpe extends Model
{
    protected $table = 'project_payment_insurance_ppe';

    protected $fillable = [
        'project_id',
        'beneficiaries_paid',
        'beneficiaries_deducted',
        'insurance_beneficiaries',
        'insurance_deducted',
        'insurance_amount',
        'ppe_deducted',
        'ppe_amount',
        'ppe_breakdown',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'beneficiaries_paid' => 'integer',
            'beneficiaries_deducted' => 'integer',
            'insurance_beneficiaries' => 'integer',
            'insurance_deducted' => 'decimal:2',
            'insurance_amount' => 'decimal:2',
            'ppe_deducted' => 'decimal:2',
            'ppe_amount' => 'decimal:2',
            'ppe_breakdown' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
