<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInsuranceEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date_enrolled',
        'beneficiary_count',
        'amount',
        'payment_mode',
        'or_number',
        'policy_number',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date_enrolled' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}