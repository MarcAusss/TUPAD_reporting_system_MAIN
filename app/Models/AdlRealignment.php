<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdlRealignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adl_id',
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