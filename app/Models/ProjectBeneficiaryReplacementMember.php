<?php

namespace App\Models;

use App\Enums\BeneficiaryReplacementRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBeneficiaryReplacementMember extends Model
{
    protected $fillable = [
        'replacement_id',
        'beneficiary_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => BeneficiaryReplacementRole::class,
        ];
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(
            ProjectBeneficiaryReplacement::class,
            'replacement_id'
        );
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(
            ProjectBeneficiary::class,
            'beneficiary_id'
        );
    }
}
