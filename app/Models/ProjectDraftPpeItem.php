<?php

namespace App\Models;

use App\Enums\PpeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDraftPpeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_draft_id',
        'ppe_type',
        'product',
        'beneficiary_count',
        'unit_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'ppe_type' => PpeType::class,
            'unit_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function projectDraft(): BelongsTo
    {
        return $this->belongsTo(ProjectDraft::class);
    }
}