<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class ProjectLocation extends Model
{
    protected $fillable = [
        'project_id',
        'province_id',
        'municipality_id',
        'district',
        'sort_order',
    ];


    protected static function booted(): void
    {
        static::saving(function (ProjectLocation $location): void {
            if (! $location->province_id || ! $location->municipality_id) {
                return;
            }

            $belongsToProvince = Municipality::query()
                ->whereKey($location->municipality_id)
                ->where('province_id', $location->province_id)
                ->exists();

            if (! $belongsToProvince) {
                throw new LogicException(
                    'The selected municipality does not belong to the selected province.'
                );
            }
        });
    }

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
            ->using(ProjectLocationBarangay::class)
            ->withPivot([
                'beneficiaries_total',
                'beneficiaries_female',
            ])
            ->withTimestamps();
    }
}
