<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Cash Advance drawn to pay CA-series GPAI enrollments. Its balance is the
 * amount less the totals of the CA enrollments charged to it; the balance is
 * what gets liquidated.
 */
class GpaiCashAdvance extends Model
{
    protected $fillable = [
        'fiscal_year',
        'cash_advance_date',
        'amount',
        'reference',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'cash_advance_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(GpaiEnrollment::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amountCents(): int
    {
        return (int) round((float) $this->amount * 100);
    }

    /** Sum of the CA enrollments charged to this cash advance, in cents. */
    public function disbursedCents(): int
    {
        $enrollments = $this->relationLoaded('enrollments')
            ? $this->enrollments
            : $this->enrollments()->with('items')->get();

        return (int) $enrollments->sum(fn (GpaiEnrollment $enrollment): int => $enrollment->totalCents());
    }

    public function balanceCents(): int
    {
        return $this->amountCents() - $this->disbursedCents();
    }

    public function label(): string
    {
        return 'CA ('.$this->cash_advance_date->format('F d, Y').')';
    }
}
