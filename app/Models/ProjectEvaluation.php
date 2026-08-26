<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}