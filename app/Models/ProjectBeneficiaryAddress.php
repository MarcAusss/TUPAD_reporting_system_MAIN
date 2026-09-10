<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProjectBeneficiaryAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'province_id',
        'municipality_id',
        'barangay_id',
        'beneficiaries_total',
        'beneficiaries_female',
        'encoded_by',
        'updated_by',
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
        static::saving(function (ProjectBeneficiaryAddress $address): void {
            if (! $address->province_id || ! $address->municipality_id || ! $address->barangay_id) {
                return;
            }

            $municipality = Municipality::query()->find($address->municipality_id);
            $barangay = Barangay::query()->find($address->barangay_id);

            if (! $municipality || (int) $municipality->province_id !== (int) $address->province_id) {
                throw new LogicException('The selected municipality does not belong to the beneficiary province.');
            }

            if (! $barangay || (int) $barangay->municipality_id !== (int) $address->municipality_id) {
                throw new LogicException('The selected barangay does not belong to the beneficiary municipality.');
            }

            if ((int) $address->beneficiaries_female > (int) $address->beneficiaries_total) {
                throw new LogicException('Female beneficiaries cannot exceed the beneficiary total for a barangay.');
            }
        });
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
