<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBarangayPpeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'barangay_id',
        'hazardous_workers',
        'complete_set_workers',
        'encoded_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hazardous_workers' => 'integer',
            'complete_set_workers' => 'integer',
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

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
