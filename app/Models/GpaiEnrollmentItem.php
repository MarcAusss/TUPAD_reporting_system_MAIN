<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One project in a GPAI enrollment transmittal. */
class GpaiEnrollmentItem extends Model
{
    protected $fillable = [
        'gpai_enrollment_id',
        'project_id',
        'adl_number',
        'particulars',
        'beneficiaries',
        'premium',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'beneficiaries' => 'integer',
            'premium' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(GpaiEnrollment::class, 'gpai_enrollment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function premiumCents(): int
    {
        return (int) round((float) $this->premium * 100);
    }
}
