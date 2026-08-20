<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectObligation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',

        'adl_number',
        'fund_sponsor',
        'partner',
        'project_location',
        'term',

        'beneficiaries_total',
        'beneficiaries_female',

        'amount',

        'obligation_date',
        'month',
        'payee',

        'remarks',

        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'obligation_date' => 'date',
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