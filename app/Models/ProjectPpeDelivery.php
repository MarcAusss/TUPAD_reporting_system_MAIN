<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPpeDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'delivery_receipt_date',
        'ppe_provided',
        'inventory_reference',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_receipt_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}