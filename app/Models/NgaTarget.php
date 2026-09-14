<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NgaTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'nga',
        'total_beneficiaries',
        'amount',
        'accomplishments',
        'balance',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_beneficiaries' => 'integer',
            'amount' => 'decimal:2',
            'accomplishments' => 'integer',
            'balance' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function accomplishmentPercent(): float
    {
        if ($this->total_beneficiaries <= 0) {
            return 0.0;
        }

        return round(
            min(100, ($this->accomplishments / $this->total_beneficiaries) * 100),
            1,
        );
    }
}
