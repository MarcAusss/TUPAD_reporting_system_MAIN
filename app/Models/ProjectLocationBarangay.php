<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LogicException;

class ProjectLocationBarangay extends Pivot
{
    protected $table = 'project_location_barangay';

    public $incrementing = true;

    protected $fillable = [
        'project_location_id',
        'barangay_id',
        'beneficiaries_total',
        'beneficiaries_female',
    ];

    protected function casts(): array
    {
        return [
            'beneficiaries_total' => 'integer',
            'beneficiaries_female' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProjectLocationBarangay $pivot): void {
            if (! $pivot->project_location_id || ! $pivot->barangay_id) {
                return;
            }

            $location = ProjectLocation::query()->find($pivot->project_location_id);
            $barangay = Barangay::query()->find($pivot->barangay_id);

            if (! $location || ! $barangay) {
                throw new LogicException('The project location or barangay reference no longer exists.');
            }

            if ((int) $barangay->municipality_id !== (int) $location->municipality_id) {
                throw new LogicException(
                    'The selected barangay does not belong to the project location municipality.'
                );
            }

            if (
                $pivot->beneficiaries_total !== null
                && $pivot->beneficiaries_female !== null
                && (int) $pivot->beneficiaries_female > (int) $pivot->beneficiaries_total
            ) {
                throw new LogicException(
                    'Female beneficiaries cannot exceed the total beneficiaries for a project location barangay.'
                );
            }
        });
    }
}
