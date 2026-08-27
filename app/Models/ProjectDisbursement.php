<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_obligation_id',
        'amount',
        'date_disbursed',
        'ldap_check_number',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date_disbursed' => 'date',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(ProjectObligation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
