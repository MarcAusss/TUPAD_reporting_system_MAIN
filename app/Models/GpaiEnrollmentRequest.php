<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A "Request GPAI Enrollment" press on a project (see GpaiEnrollmentService). */
class GpaiEnrollmentRequest extends Model
{
    protected $fillable = [
        'project_id',
        'requested_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
