<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ProjectEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'findings',
        'required_documents',
        'remarks',
        'result',
        'evaluated_by',
        'evaluated_at',
        'compliance_date',
        'compliance_remarks',
        'complied_by',
        'complied_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
            'compliance_date' => 'date',
            'complied_at' => 'datetime',
        ];
    }

    /** All files on this evaluation (TSSD evaluation and TC compliance uploads). */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectEvaluationAttachment::class)->orderBy('id');
    }

    /** Files TSSD attached to the For Compliance findings / required documents. */
    public function evaluationAttachments(): Collection
    {
        return $this->attachments->where('kind', ProjectEvaluationAttachment::KIND_EVALUATION)->values();
    }

    /** Files the TUPAD Coordinator attached to the compliance submission. */
    public function complianceAttachments(): Collection
    {
        return $this->attachments->where('kind', ProjectEvaluationAttachment::KIND_COMPLIANCE)->values();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'evaluated_by'
        );
    }

    public function complier(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'complied_by'
        );
    }

    /**
     * Whether this "for compliance" finding has already been resolved.
     */
    public function isComplied(): bool
    {
        return $this->complied_at !== null;
    }

    /**
     * Days between the TSSD finding and its resolution.
     *
     * For an already-complied finding, this is how long it took to comply
     * (evaluated_at -> compliance_date). For one still pending, this is how
     * long it has been sitting unresolved (evaluated_at -> today).
     */
    public function agingDays(): int
    {
        $end = $this->compliance_date ?? now();

        return (int) $this->evaluated_at
            ->copy()
            ->startOfDay()
            ->diffInDays(
                $end->copy()->startOfDay()
            );
    }
}