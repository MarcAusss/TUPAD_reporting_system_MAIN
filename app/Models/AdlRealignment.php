<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdlRealignment extends Model
{
    use HasFactory;

    public const DIRECTION_TUPAD_TO_GIP = 'tupad_to_gip';

    public const DIRECTION_GIP_TO_TUPAD = 'gip_to_tupad';

    protected $fillable = [
        'adl_id',
        'direction',
        'amount',
        'reference_number',
        'realignment_date',
        'maf_date',
        'maf_number',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'realignment_date' => 'date',
            'maf_date' => 'date',
        ];
    }

    public function getDirectionLabelAttribute(): string
    {
        return match ($this->direction) {
            self::DIRECTION_TUPAD_TO_GIP =>
                'TUPAD to GIP',

            self::DIRECTION_GIP_TO_TUPAD =>
                'GIP to TUPAD',

            default =>
                'Legacy Realignment',
        };
    }

    public function getAbsoluteAmountAttribute(): float
    {
        return abs(
            (float) $this->amount
        );
    }

    public function adl(): BelongsTo
    {
        return $this->belongsTo(Adl::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}