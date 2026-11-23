<?php

namespace App\Models;

use App\Enums\ProjectTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Region-wide beneficiary target for one fiscal year and term. */
class BeneficiaryTarget extends Model
{
    protected $fillable = [
        'fiscal_year',
        'term',
        'target',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'term' => ProjectTerm::class,
            'target' => 'integer',
        ];
    }

    /** @return array<string, int> term value => target, only for saved terms */
    public static function forYear(int $year): array
    {
        return static::query()
            ->where('fiscal_year', $year)
            ->get()
            ->mapWithKeys(fn (self $row): array => [$row->term->value => $row->target])
            ->all();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
