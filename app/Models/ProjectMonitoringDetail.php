<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMonitoringDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'project_series', 'proponent', 'receipt_month', 'receipt_datetime',
        'process_cycle_days', 'compliance_date', 'compliance_reference', 'agreement_type',
        'agreement_date', 'agreement_reference', 'replacement_request_date',
        'replacement_ntp_date', 'voucher_date', 'voucher_number', 'nafa_date', 'nafa_number',
        'sprs_date', 'cqpr_date', 'transparency_seal_date', 'monitoring_remarks', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_datetime' => 'datetime',
            'compliance_date' => 'date',
            'agreement_date' => 'date',
            'replacement_request_date' => 'date',
            'replacement_ntp_date' => 'date',
            'voucher_date' => 'date',
            'nafa_date' => 'date',
            'sprs_date' => 'date',
            'cqpr_date' => 'date',
            'transparency_seal_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
