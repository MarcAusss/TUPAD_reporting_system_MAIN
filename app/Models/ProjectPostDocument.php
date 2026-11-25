<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectPostDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'tranche_number',
        'beneficiaries_total',
        'beneficiaries_female',
        'coverage_start_date',
        'coverage_end_date',
        'date_received',
        'document_type',
        'attachment_path',
        'remarks',
        'date_forwarded_to_imsd',
        'recorded_by',
        'coverage_updated_by',
        'coverage_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'tranche_number' => 'integer',
            'beneficiaries_total' => 'integer',
            'beneficiaries_female' => 'integer',
            'coverage_start_date' => 'date',
            'coverage_end_date' => 'date',
            'date_received' => 'date',
            'date_forwarded_to_imsd' => 'date',
            'coverage_updated_at' => 'datetime',
        ];
    }

    /**
     * Long-term tranche submission: the TUPAD Coordinator states the
     * tranche's beneficiaries and coverage dates for the Focal's payment.
     */
    public function isTranche(): bool
    {
        return $this->tranche_number !== null;
    }

    /** Calendar days the tranche covers, start and end dates included. */
    public function coverageDays(): ?int
    {
        if ($this->coverage_start_date === null || $this->coverage_end_date === null) {
            return null;
        }

        return (int) $this->manilaDate($this->coverage_start_date)
            ->diffInDays($this->manilaDate($this->coverage_end_date)) + 1;
    }

    /**
     * Days left until the coverage end date (Asia/Manila): 0 once the end
     * date is reached, and never more than the days the tranche covers.
     */
    public function balanceDays(?CarbonInterface $today = null): ?int
    {
        $coverageDays = $this->coverageDays();

        if ($coverageDays === null) {
            return null;
        }

        $today = $today
            ? CarbonImmutable::instance($today)->setTimezone('Asia/Manila')->startOfDay()
            : CarbonImmutable::now('Asia/Manila')->startOfDay();

        $left = (int) $today->diffInDays($this->manilaDate($this->coverage_end_date), false);

        return max(0, min($coverageDays, $left));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The Focal's obligation tranche created from this submission. */
    public function obligation(): HasOne
    {
        return $this->hasOne(ProjectObligation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }

    public function coverageUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coverage_updated_by');
    }

    private function manilaDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'), 'Asia/Manila')->startOfDay();
    }
}
