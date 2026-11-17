<?php

namespace App\Models;

use App\Enums\ProjectTerm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReformulatedTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'fiscal_year',
        'physical_target',
        'financial_target_cents',
        'short_term_physical_target',
        'short_term_financial_target_cents',
        'long_term_physical_target',
        'long_term_financial_target_cents',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'physical_target' => 'integer',
            'financial_target_cents' => 'integer',
            'short_term_physical_target' => 'integer',
            'short_term_financial_target_cents' => 'integer',
            'long_term_physical_target' => 'integer',
            'long_term_financial_target_cents' => 'integer',
        ];
    }

    /** True once Focal has saved separate short-term and long-term targets. */
    public function hasTermSplit(): bool
    {
        return $this->short_term_physical_target !== null
            && $this->short_term_financial_target_cents !== null
            && $this->long_term_physical_target !== null
            && $this->long_term_financial_target_cents !== null;
    }

    /** @return array{physical:int, financial_cents:int}|null */
    public function termTarget(ProjectTerm $term): ?array
    {
        if (! $this->hasTermSplit()) {
            return null;
        }

        return $term === ProjectTerm::SHORT_TERM
            ? ['physical' => $this->short_term_physical_target, 'financial_cents' => $this->short_term_financial_target_cents]
            : ['physical' => $this->long_term_physical_target, 'financial_cents' => $this->long_term_financial_target_cents];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
