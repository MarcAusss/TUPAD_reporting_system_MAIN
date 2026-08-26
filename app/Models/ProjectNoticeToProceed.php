<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNoticeToProceed extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Explicit Table Name
    |--------------------------------------------------------------------------
    |
    | Laravel's pluralizer infers this class as "project_notices_to_proceed",
    | while the existing migration correctly created:
    |
    | project_notice_to_proceeds
    |
    | Bind the model explicitly so all implementation workflows use the
    | existing table.
    |
    */

    protected $table = 'project_notice_to_proceeds';

    protected $fillable = [
        'project_id',
        'date_issued',
        'date_released',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'date_released' => 'date',
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