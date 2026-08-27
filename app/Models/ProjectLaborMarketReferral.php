<?php

namespace App\Models;

use App\Enums\LaborMarketProgram;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectLaborMarketReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'reporting_month',
        'program',
        'interested_referred_total',
        'interested_referred_female',
        'provided_intervention_total',
        'provided_intervention_female',
        'amount_released',
        'services_availed',
        'recorded_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reporting_month' => 'date',
            'program' => LaborMarketProgram::class,
            'interested_referred_total' => 'integer',
            'interested_referred_female' => 'integer',
            'provided_intervention_total' => 'integer',
            'provided_intervention_female' => 'integer',
            'amount_released' => 'decimal:2',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
