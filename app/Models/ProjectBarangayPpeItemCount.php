<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBarangayPpeItemCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'barangay_id',
        'project_ppe_item_id',
        'recipients',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function ppeItem(): BelongsTo
    {
        return $this->belongsTo(ProjectPpeItem::class, 'project_ppe_item_id');
    }
}
