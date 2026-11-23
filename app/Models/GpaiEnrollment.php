<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A GPAI enrollment transmittal: a bulk enrollment of TUPAD projects to GSIS
 * Group Personal Accident Insurance, paid by Voucher (GPAI V26-0001) or from
 * a Cash Advance (GPAI CA26-001).
 */
class GpaiEnrollment extends Model
{
    public const MODE_VOUCHER = 'voucher';

    public const MODE_CASH_ADVANCE = 'ca';

    /** GPAI premium per insured beneficiary used in the enrollment report (₱50). */
    public const REPORT_RATE = 50;

    /** GSIS documentary stamp tax per transmittal: 20% of the premium, capped. */
    public const DOC_STAMP_CAP = 200.00;

    public const STAGES = [
        'prepared' => 'Transmittal Prepared',
        'for_billing' => 'Awaiting SOA / Billing',
        'for_routing' => 'For Routing',
        'for_payment' => 'For Payment',
        'for_or_policy' => 'For OR and Policy',
        'complete' => 'Complete',
    ];

    /** Status of the first Yearly Status table (billing and routing). */
    public const BILLING_STATUSES = [
        'rd' => 'RD',
        'ard' => 'ARD',
        'cashier' => 'Cashier',
        'accounting' => 'Accounting',
        'tssd_chief' => 'TSSD Chief',
        'imsd' => 'IMSD',
        'signed_completed' => 'Signed/Completed',
        'for_deposit' => 'For Deposit',
        'for_pickup_ops' => 'For Pick up of OPS',
        'for_soa' => 'For SOA',
        'for_transmittal_signing' => 'For Transmittal Signing',
        'for_dv_ors_signing' => 'For DV and ORS Signing',
        'for_checklist_signing' => 'For Checklist Signing',
        'budget' => 'Budget',
    ];

    /** A transmittal moves to the On-call Payment Slip table once Signed/Completed. */
    public const BILLING_SIGNED = 'signed_completed';

    /** Table 1 remark: how the billing documents were received. */
    public const RECEIVED_VIA = [
        'imsd' => 'IMSD',
        'email' => 'Email',
    ];

    /** Status of the second Yearly Status table (on-call payment slip). */
    public const OPS_STATUSES = [
        'complete' => 'Complete',
        'for_or_and_policy' => 'For OR and Policy',
        'for_or_only' => 'For OR only',
        'for_policy_only' => 'For Policy only',
    ];

    protected $fillable = [
        'gpai_number',
        'payment_mode',
        'fiscal_year',
        'sequence',
        'transmittal_date',
        'coverage_start',
        'coverage_end',
        'doc_stamp',
        'gpai_cash_advance_id',
        'billing_emailed_at',
        'soa_received_at',
        'routing_released_on',
        'payment_slip_on',
        'payment_slip_validated_at',
        'or_policy_requested_at',
        'or_policy_received_on',
        'distributed_on',
        'billing_status',
        'billing_received_via',
        'ops_status',
        'ops_remarks',
        'ops_notes',
        'ops_document_url',
        'or_number',
        'policy_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'sequence' => 'integer',
            'transmittal_date' => 'date',
            'coverage_start' => 'date',
            'coverage_end' => 'date',
            'doc_stamp' => 'decimal:2',
            'billing_emailed_at' => 'datetime',
            'soa_received_at' => 'datetime',
            'routing_released_on' => 'date',
            'payment_slip_on' => 'date',
            'payment_slip_validated_at' => 'datetime',
            'or_policy_requested_at' => 'datetime',
            'or_policy_received_on' => 'date',
            'distributed_on' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GpaiEnrollmentItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(GpaiCashAdvance::class, 'gpai_cash_advance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function modeLabel(string $mode): string
    {
        return $mode === self::MODE_CASH_ADVANCE ? 'Cash Advance' : 'Voucher';
    }

    /** "GPAI V26-0001" (voucher) or "GPAI CA26-001" (cash advance). */
    public static function formatNumber(string $mode, int $fiscalYear, int $sequence): string
    {
        $year = substr((string) $fiscalYear, -2);

        return $mode === self::MODE_CASH_ADVANCE
            ? sprintf('GPAI CA%s-%03d', $year, $sequence)
            : sprintf('GPAI V%s-%04d', $year, $sequence);
    }

    /** Doc. stamp default: 20% of the premium, capped at 200.00 (as in the 2026 sheets). */
    public static function defaultDocStamp(float $premium): float
    {
        return min(self::DOC_STAMP_CAP, round($premium * 0.20, 2));
    }

    public function stage(): string
    {
        return match (true) {
            $this->or_policy_received_on !== null => 'complete',
            $this->or_policy_requested_at !== null => 'for_or_policy',
            $this->routing_released_on !== null, $this->payment_slip_on !== null => 'for_payment',
            $this->soa_received_at !== null => 'for_routing',
            $this->billing_emailed_at !== null => 'for_billing',
            default => 'prepared',
        };
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage()];
    }

    /**
     * The GSIS processing steps in order: field => [label, input type].
     * Matches the 2026 STATUS sheet columns.
     */
    public const STEPS = [
        'billing_emailed_at' => ['Emailed for Issuance of Insurance Bill', 'datetime-local'],
        'soa_received_at' => ['SOA / Billing Received', 'datetime-local'],
        'routing_released_on' => ['Released for Routing', 'date'],
        'payment_slip_on' => ['On-call Payment Slip Date', 'date'],
        'payment_slip_validated_at' => ['Validated On-call Payment Slip Received (Cashier)', 'datetime-local'],
        'or_policy_requested_at' => ['Emailed GSIS for Issuance of OR & Policy', 'datetime-local'],
        'or_policy_received_on' => ['OR and Policy Received (Complete)', 'date'],
        // Automatic: set when the TUPAD Coordinators are notified (always the last step).
        'distributed_on' => ['Distributed to PCs', 'auto'],
    ];

    /**
     * The next step to record: the first empty step after the last one
     * recorded (steps may be skipped, e.g. direct payment with no SOA).
     * Null once every step is recorded.
     */
    public function nextStep(): ?string
    {
        $fields = array_keys(self::STEPS);
        $lastRecorded = -1;

        foreach ($fields as $index => $field) {
            if ($this->{$field} !== null) {
                $lastRecorded = $index;
            }
        }

        $next = $fields[$lastRecorded + 1] ?? null;

        // Automatic steps are never asked for.
        return $next !== null && self::STEPS[$next][1] === 'auto' ? null : $next;
    }

    public function nextStepLabel(): ?string
    {
        $next = $this->nextStep();

        return $next ? self::STEPS[$next][0] : null;
    }

    public function billingStatusLabel(): ?string
    {
        return self::BILLING_STATUSES[$this->billing_status] ?? null;
    }

    public function receivedViaLabel(): ?string
    {
        return self::RECEIVED_VIA[$this->billing_received_via] ?? null;
    }

    public function opsStatusLabel(): ?string
    {
        return self::OPS_STATUSES[$this->ops_status] ?? null;
    }

    /** Signed/Completed transmittals proceed to the On-call Payment Slip table. */
    public function isBillingSigned(): bool
    {
        return $this->billing_status === self::BILLING_SIGNED;
    }

    /** Validation rule: an https link on Google Drive / Google Docs. */
    public static function googleDriveLinkRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            $scheme = strtolower((string) parse_url((string) $value, PHP_URL_SCHEME));

            if ($scheme !== 'https' || ! in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
                $fail('The File / Document must be a Google Drive link (https://drive.google.com/… or https://docs.google.com/…).');
            }
        };
    }

    public function isComplete(): bool
    {
        return $this->stage() === 'complete';
    }

    public function beneficiaries(): int
    {
        return (int) $this->items->sum('beneficiaries');
    }

    public function premiumCents(): int
    {
        return (int) $this->items->sum(fn (GpaiEnrollmentItem $item): int => $item->premiumCents());
    }

    public function docStampCents(): int
    {
        return (int) round((float) $this->doc_stamp * 100);
    }

    public function totalCents(): int
    {
        return $this->premiumCents() + $this->docStampCents();
    }

    /** "DOLE TUPAD Project for 199 beneficiaries in Albay and Camarines Sur". */
    public function natureOfPayment(): string
    {
        $provinces = $this->items
            ->map(fn (GpaiEnrollmentItem $item): ?string => $item->project?->province)
            ->filter()
            ->unique()
            ->values();

        $places = match ($provinces->count()) {
            0 => 'Region V',
            1 => $provinces->first(),
            default => $provinces->slice(0, -1)->implode(', ').' and '.$provinces->last(),
        };

        return sprintf('DOLE TUPAD Project for %s beneficiaries in %s', number_format($this->beneficiaries()), $places);
    }
}
