<?php

namespace App\Models;

use App\Enums\BeneficiarySectorCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBeneficiarySector extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'sector_group',
        'sector_key',
        'beneficiaries_total',
        'beneficiaries_female',
        'recorded_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sector_key' => BeneficiarySectorCategory::class,
            'beneficiaries_total' => 'integer',
            'beneficiaries_female' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
