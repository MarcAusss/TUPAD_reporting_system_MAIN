<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectLocation extends Model
{
    protected $fillable = [
        'project_id',
        'province_id',
        'municipality_id',
        'district',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function barangays(): BelongsToMany
    {
        return $this->belongsToMany(
            Barangay::class,
            'project_location_barangay'
        )
            ->withPivot([
                'beneficiaries_total',
                'beneficiaries_female',
            ])
            ->withTimestamps();
    }
}
