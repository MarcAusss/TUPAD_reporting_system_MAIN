<?php

namespace App\Services\Projects;

use App\Http\Controllers\ProjectReleaseOfAssistanceController;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectEvaluation;
use App\Models\ProjectObligation;
use App\Services\Payments\ProjectPaymentService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Every workflow step shown as its own section in the project overview,
 * with the fields that can be corrected through the section editor.
 *
 * Field types: text, textarea, date, number, money, select, boolean, payout_mode.
 * Derived values (project costs, tranche totals, project codes, evaluation
 * results) are shown read-only as "extra" rows and are never edited directly.
 */
final class ProjectSectionRegistry
{
    private const MONEY_RULE = 'regex:/^\d{1,13}(?:\.\d{1,2})?$/';

    public function __construct(
        private readonly ProjectPaymentService $payments,
        private readonly ProjectBeneficiaryDeductionService $deductions,
    ) {
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            'project' => [
                'label' => 'Project Profile',
                'records' => fn (Project $project): Collection => collect([$project]),
                'title' => fn (): string => 'Project Profile (Create Project)',
                'fields' => [
                    'project_title' => ['label' => 'Project Title', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'date_received' => ['label' => 'Date Received', 'type' => 'date', 'rules' => ['required', 'date']],
                    'nature_of_work' => ['label' => 'Nature of Work', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:3000']],
                    'fund_sponsor' => ['label' => 'Fund Sponsor', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'partner' => ['label' => 'Partner', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'program' => ['label' => 'Program', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'project_series' => ['label' => 'Project Series', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'project_series_remarks' => ['label' => 'Series Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                    'tevs_date_verified' => ['label' => 'TEVS Date Verified', 'type' => 'date', 'rules' => ['required', 'date']],
                    'tevs_remarks' => ['label' => 'TEVS Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'extra' => fn (Project $project): array => [
                    'Implementation Mode' => $project->implementation_mode?->label(),
                    'Location' => $project->payment_location_summary ?: collect([$project->barangay, $project->municipality, $project->province])->filter()->implode(', '),
                    'Duration' => $project->number_of_days.' days · '.$project->term?->label(),
                    'Beneficiaries' => number_format((int) $project->beneficiaries_total).' ('.number_format((int) $project->beneficiaries_female).' female)',
                    'Wages' => $this->peso($project->wages_total),
                    'PPE' => $this->peso($project->ppe_total),
                    'Insurance' => $this->peso($project->insurance_total),
                    'Total Project Cost' => $this->peso($project->total_project_cost),
                ],
            ],

            'evaluation' => [
                'label' => 'TSSD Evaluation',
                'records' => fn (Project $project): Collection => $project->evaluations->sortBy('id')->values(),
                'title' => fn (ProjectEvaluation $evaluation, int $position): string => 'TSSD Evaluation #'.$position,
                'fields' => [
                    'findings' => ['label' => 'Findings', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
                    'required_documents' => ['label' => 'Required Documents', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
                ],
                'attachments' => fn (ProjectEvaluation $evaluation, Project $project): array => $this->attachmentLinks(
                    $evaluation->evaluationAttachments(),
                    $project,
                ),
                'extra' => fn (ProjectEvaluation $evaluation): array => [
                    'Result' => match ($evaluation->result) {
                        'for_compliance' => 'For Compliance',
                        'for_approval' => 'For Approval',
                        default => (string) $evaluation->result,
                    },
                    'Evaluated' => $evaluation->evaluated_at?->format('F d, Y h:i A'),
                ],
            ],

            'compliance' => [
                'label' => 'Compliance',
                'records' => fn (Project $project): Collection => $project->evaluations
                    ->filter(fn (ProjectEvaluation $evaluation): bool => $evaluation->compliance_date !== null)
                    ->sortBy('id')
                    ->values(),
                'title' => fn (ProjectEvaluation $evaluation, int $position): string => 'Compliance #'.$position,
                'fields' => [
                    'compliance_date' => ['label' => 'Compliance Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'compliance_remarks' => ['label' => 'Compliance Remarks', 'type' => 'textarea', 'rules' => ['required', 'string', 'max:5000']],
                ],
                'attachments' => fn (ProjectEvaluation $evaluation, Project $project): array => $this->attachmentLinks(
                    $evaluation->complianceAttachments(),
                    $project,
                ),
            ],

            'approval' => [
                'label' => 'Approval',
                'records' => fn (Project $project): Collection => collect([$project->approval])->filter(),
                'title' => fn (): string => 'Project Approval',
                'fields' => [
                    'approval_date' => ['label' => 'Approval Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:5000']],
                ],
                'extra' => fn (Model $approval): array => [
                    'Project Code' => $approval->project_code,
                ],
            ],

            'insurance' => [
                'label' => 'Insurance Enrollment',
                'records' => fn (Project $project): Collection => collect([$project->insuranceEnrollment])->filter(),
                'title' => fn (): string => 'Insurance Enrollment',
                'fields' => [
                    'date_enrolled' => ['label' => 'Date Enrolled', 'type' => 'date', 'rules' => ['required', 'date']],
                    'payment_mode' => [
                        'label' => 'Payment Mode',
                        'type' => 'select',
                        'options' => ['voucher' => 'Voucher', 'ca' => 'Cash Advance'],
                        'rules' => ['required', Rule::in(['voucher', 'ca'])],
                    ],
                    'or_number' => ['label' => 'OR Number', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
                    'policy_number' => ['label' => 'Policy Number', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'extra' => fn (Model $enrollment): array => [
                    'Insured Beneficiaries' => number_format((int) $enrollment->beneficiary_count),
                    'Amount' => $this->peso($enrollment->amount),
                ],
            ],

            'ppe_delivery' => [
                'label' => 'PPE Delivery',
                'records' => fn (Project $project): Collection => $project->ppeDeliveries->sortBy('id')->values(),
                'title' => fn (Model $delivery, int $position): string => 'PPE Delivery #'.$position,
                'fields' => [
                    'delivery_receipt_date' => ['label' => 'Delivery Receipt Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'inventory_reference' => ['label' => 'Inventory Reference', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'extra' => fn (Model $delivery): array => [
                    'Items Delivered' => $delivery->items
                        ->map(fn ($item): string => ($item->ppeItem?->product ?? $item->ppeItem?->name ?? 'PPE').' × '.number_format((int) $item->quantity))
                        ->implode(', ') ?: ($delivery->ppe_provided ?: '—'),
                ],
            ],

            'nafa' => [
                'label' => 'NAFA',
                'records' => fn (Project $project): Collection => collect([$project->nafa])->filter(),
                'title' => fn (): string => 'NAFA (Notice of Availability of Fund)',
                'fields' => [
                    'nafa_date' => ['label' => 'Date of NAFA', 'type' => 'date', 'rules' => ['required', 'date']],
                    'release_date' => ['label' => 'Release Date', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:nafa_date']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'attachments' => fn (Model $nafa, Project $project): array => $nafa->attachments
                    ->map(fn ($attachment): array => [
                        'name' => $attachment->original_name,
                        'url' => route('projects.nafa.attachments.download', [$project, $attachment]),
                    ])
                    ->values()
                    ->all(),
            ],

            'notice_to_proceed' => [
                'label' => 'Notice to Proceed',
                'records' => fn (Project $project): Collection => collect([$project->noticeToProceed])->filter(),
                'title' => fn (): string => 'Notice to Proceed',
                'fields' => [
                    'date_issued' => ['label' => 'Date Issued', 'type' => 'date', 'rules' => ['required', 'date']],
                    'date_released' => ['label' => 'Date Released', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:date_issued']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
            ],

            'orientation' => [
                'label' => 'Orientation',
                'records' => fn (Project $project): Collection => collect([$project->orientation])->filter(),
                'title' => fn (): string => 'Orientation',
                'fields' => [
                    'orientation_date' => ['label' => 'Orientation Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'beneficiaries_oriented' => [
                        'label' => 'Beneficiaries Oriented',
                        'type' => 'number',
                        'rules' => fn (Project $project): array => ['required', 'integer', 'min:0', 'lte:'.(int) $project->beneficiaries_total],
                    ],
                    'venue' => ['label' => 'Venue', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'oriented_by' => ['label' => 'Oriented By', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'alkansssya_conducted' => ['label' => 'AlkanSSSya Conducted', 'type' => 'boolean', 'rules' => ['boolean']],
                    'yakap_conducted' => ['label' => 'YAKAP Conducted', 'type' => 'boolean', 'rules' => ['boolean']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
            ],

            'implementation' => [
                'label' => 'Work Period',
                'records' => fn (Project $project): Collection => collect([$project->implementation])->filter(),
                'title' => fn (): string => 'Implementation Work Period',
                'fields' => [
                    'start_date' => ['label' => 'Start Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'end_date' => ['label' => 'End Date', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:start_date']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'sync_status' => true,
            ],

            'post_document' => [
                'label' => 'Post-Documentary Requirements',
                'records' => fn (Project $project): Collection => $project->postDocuments->sortBy('id')->values(),
                'title' => fn (Model $document, int $position): string => 'Post-Documentary Submission #'.$position,
                'fields' => [
                    'date_received' => ['label' => 'Date Received', 'type' => 'date', 'rules' => ['required', 'date']],
                    'document_type' => ['label' => 'Document Type', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                    'date_forwarded_to_imsd' => ['label' => 'Date Forwarded to IMSD', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:date_received']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'sync_status' => true,
            ],

            'obligation' => [
                'label' => 'Obligation Tranche',
                'records' => fn (Project $project): Collection => $project->obligations->sortBy('tranche_number')->values(),
                'title' => fn (ProjectObligation $obligation): string => 'Obligation – Tranche '.$obligation->tranche_number,
                'fields' => [
                    'beneficiaries_total' => ['label' => 'No. of Beneficiaries', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1']],
                    'beneficiaries_female' => ['label' => 'Female', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'lte:beneficiaries_total']],
                    'wages_amount' => ['label' => 'Wages', 'type' => 'money', 'rules' => ['required', self::MONEY_RULE]],
                    'insurance_amount' => ['label' => 'Insurance', 'type' => 'money', 'rules' => ['required', self::MONEY_RULE]],
                    'ppe_amount' => ['label' => 'PPE (Total Amount)', 'type' => 'money', 'rules' => ['required', self::MONEY_RULE]],
                    'obligation_date' => ['label' => 'Obligation Date', 'type' => 'date', 'rules' => ['required', 'date']],
                    'payee' => ['label' => 'Payee', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'extra' => fn (ProjectObligation $obligation): array => [
                    'Total Overall' => $this->peso($obligation->amount),
                    'Disbursed' => $this->peso($this->payments->disbursedForObligationCents($obligation) / 100),
                ],
                'validate' => fn (Project $project, ProjectObligation $obligation, array $data) => $this->validateObligation($project, $obligation, $data),
                'save' => function (Project $project, ProjectObligation $obligation, array $data): void {
                    $total = $this->payments->amountToCents($data['wages_amount'])
                        + $this->payments->amountToCents($data['insurance_amount'])
                        + $this->payments->amountToCents($data['ppe_amount']);

                    $obligation->update($data + [
                        'amount' => $this->payments->centsToDecimal($total),
                        'month' => date('F Y', strtotime($data['obligation_date'])),
                    ]);
                },
                'sync_status' => true,
            ],

            'disbursement' => [
                'label' => 'Disbursement',
                'records' => fn (Project $project): Collection => $project->obligations
                    ->sortBy('tranche_number')
                    ->flatMap(fn (ProjectObligation $obligation): Collection => $obligation->disbursements)
                    ->values(),
                'title' => fn (ProjectDisbursement $disbursement, int $position, Project $project): string => sprintf(
                    'Disbursement – Tranche %d (%s)',
                    (int) $project->obligations->firstWhere('id', $disbursement->project_obligation_id)?->tranche_number,
                    $disbursement->ldap_check_number,
                ),
                'fields' => [
                    'amount' => ['label' => 'Amount Disbursed', 'type' => 'money', 'rules' => ['required', self::MONEY_RULE]],
                    'date_disbursed' => ['label' => 'Date Disbursed', 'type' => 'date', 'rules' => ['required', 'date']],
                    'ldap_check_number' => ['label' => 'LDAP / Check Number', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'validate' => fn (Project $project, ProjectDisbursement $disbursement, array $data) => $this->validateDisbursement($disbursement, $data),
                'sync_status' => true,
            ],

            // Through ACP: one project-level Release of Assistance (project_payouts).
            'acp_release' => [
                'label' => 'Release of Assistance',
                'records' => fn (Project $project): Collection => $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP
                    ? collect([$project->payout])->filter()
                    : collect(),
                'title' => fn (): string => 'Release of Assistance (Through ACP)',
                'fields' => [
                    'payout_mode' => ['label' => 'Mode of Payment', 'type' => 'payout_mode', 'rules' => ['required', 'string', 'max:100']],
                    'payout_date' => ['label' => 'Date of Payout', 'type' => 'date', 'rules' => ['required', 'date']],
                    'venue' => ['label' => 'Venue', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'sync_status' => true,
            ],

            // Custom section: rendered by its own view and saved by
            // ProjectBeneficiaryDeductionController (per-address rows).
            'beneficiary_deduction' => [
                'label' => 'Actual Beneficiary Mapping',
                'records' => fn (Project $project): Collection => $this->deductions->needsDeduction($project)
                    ? collect([$project])
                    : collect(),
                'title' => fn (): string => 'Beneficiary Deduction (Actual Beneficiary Mapping)',
                'fields' => [],
                'view' => 'projects.partials.beneficiary-deduction',
                // The TC records the first entry without approval.
                'first_entry_open' => fn (Project $project): bool => ! $this->deductions->isRecorded($project),
            ],

            'release' => [
                'label' => 'Release of Assistance',
                'records' => fn (Project $project): Collection => $project->obligations
                    ->filter(fn (ProjectObligation $obligation): bool => $obligation->isReleased())
                    ->sortBy('tranche_number')
                    ->values(),
                'title' => fn (ProjectObligation $obligation): string => 'Release of Assistance – Tranche '.$obligation->tranche_number,
                'fields' => [
                    'release_mode' => ['label' => 'Mode of Payment', 'type' => 'payout_mode', 'rules' => ['required', 'string', 'max:100']],
                    'release_date' => ['label' => 'Date of Payout', 'type' => 'date', 'rules' => ['required', 'date']],
                    'release_venue' => ['label' => 'Venue', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'release_remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:3000']],
                ],
                'sync_status' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function definition(string $key): array
    {
        return $this->definitions()[$key] ?? abort(404);
    }

    /**
     * All sections with data, in workflow order.
     *
     * @return list<array{key:string,label:string,title:string,anchor:string,record:Model,definition:array<string,mixed>}>
     */
    public function instancesFor(Project $project): array
    {
        $instances = [];

        foreach ($this->definitions() as $key => $definition) {
            $definition['records']($project)->each(function (Model $record, int $index) use (&$instances, $key, $definition, $project): void {
                $instances[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'title' => $definition['title']($record, $index + 1, $project),
                    'anchor' => 'section-'.str_replace('_', '-', $key).'-'.$record->getKey(),
                    'record' => $record,
                    'definition' => $definition,
                ];
            });
        }

        return $instances;
    }

    public function resolveRecord(Project $project, string $key, int $recordId): Model
    {
        $definition = $this->definition($key);

        return $definition['records']($project)->first(fn (Model $record): bool => (int) $record->getKey() === $recordId)
            ?? abort(404);
    }

    /** @return array<string, mixed> */
    public function rules(array $definition, Project $project): array
    {
        $rules = [];

        foreach ($definition['fields'] as $name => $field) {
            $rules[$name] = is_callable($field['rules']) ? $field['rules']($project) : $field['rules'];

            if ($field['type'] === 'payout_mode') {
                $rules[$name.'_other'] = [
                    'nullable',
                    'required_if:'.$name.','.ProjectReleaseOfAssistanceController::OTHER_MODE,
                    'string',
                    'max:'.(100 - strlen(ProjectReleaseOfAssistanceController::OTHER_PREFIX)),
                ];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributeNames(array $definition): array
    {
        return collect($definition['fields'])
            ->mapWithKeys(fn (array $field, string $name): array => [$name => strtolower($field['label'])])
            ->all();
    }

    /**
     * Normalize submitted input: strip commas from money, cast booleans, and
     * combine "Others, specify" payout modes.
     *
     * @return array<string, mixed>
     */
    public function prepareInput(array $definition, array $input): array
    {
        foreach ($definition['fields'] as $name => $field) {
            $value = $input[$name] ?? null;

            $input[$name] = match ($field['type']) {
                'money' => $value === null ? null : str_replace([',', ' '], '', trim((string) $value)),
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'text', 'textarea' => $value === null ? null : (trim((string) $value) === '' ? null : trim((string) $value)),
                default => $value,
            };
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function valuesToSave(array $definition, array $validated): array
    {
        $data = [];

        foreach ($definition['fields'] as $name => $field) {
            $value = $validated[$name] ?? null;

            if ($field['type'] === 'payout_mode' && $value === ProjectReleaseOfAssistanceController::OTHER_MODE) {
                $value = ProjectReleaseOfAssistanceController::OTHER_PREFIX.trim((string) ($validated[$name.'_other'] ?? ''));
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /** Value suitable for pre-filling the edit form. */
    public function formValue(array $field, Model $record, string $name): mixed
    {
        $value = $record->getAttribute($name);

        return match ($field['type']) {
            'date' => $value instanceof CarbonInterface ? $value->toDateString() : $value,
            'money' => $value === null ? null : number_format((float) $value, 2, '.', ''),
            'boolean' => (bool) $value,
            default => $value,
        };
    }

    /** Human-readable value for the overview and change log. */
    public function display(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field['type']) {
            'date' => ($value instanceof CarbonInterface ? $value : \Illuminate\Support\Carbon::parse($value))->format('F d, Y'),
            'money' => $this->peso($value),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No',
            'select' => (string) ($field['options'][$value] ?? $value),
            'number' => number_format((int) $value),
            default => (string) $value,
        };
    }

    /**
     * @param  Collection<int, \App\Models\ProjectEvaluationAttachment>  $attachments
     * @return list<array{name:string,url:string}>
     */
    private function attachmentLinks(Collection $attachments, Project $project): array
    {
        return $attachments
            ->map(fn ($attachment): array => [
                'name' => $attachment->original_name,
                'url' => route('projects.compliance.attachments.download', [$project, $attachment]),
            ])
            ->values()
            ->all();
    }

    private function peso(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    private function validateObligation(Project $project, ProjectObligation $obligation, array $data): void
    {
        $limits = $this->payments->obligationLimits($project);
        $others = $this->payments->obligatedTotals(
            $project->obligations()->whereKeyNot($obligation->getKey())->get()
        );

        $wages = $this->payments->amountToCents($data['wages_amount']);
        $insurance = $this->payments->amountToCents($data['insurance_amount']);
        $ppe = $this->payments->amountToCents($data['ppe_amount']);
        $total = $wages + $insurance + $ppe;

        if ($total <= 0) {
            throw ValidationException::withMessages(['wages_amount' => 'The tranche total must be greater than zero.']);
        }

        $checks = [
            'beneficiaries_total' => ['beneficiaries_total', (int) $data['beneficiaries_total'], 'number of beneficiaries', false],
            'beneficiaries_female' => ['beneficiaries_female', (int) $data['beneficiaries_female'], 'female beneficiaries', false],
            'wages' => ['wages_amount', $wages, 'wages', true],
            'insurance' => ['insurance_amount', $insurance, 'insurance', true],
            'ppe' => ['ppe_amount', $ppe, 'PPE amount', true],
            'total' => ['wages_amount', $total, 'Total Project Cost', true],
        ];

        foreach ($checks as $key => [$field, $value, $label, $money]) {
            if ($others[$key] + $value > $limits[$key]) {
                $remaining = max(0, $limits[$key] - $others[$key]);

                throw ValidationException::withMessages([
                    $field => sprintf(
                        'This exceeds the project\'s %s. Remaining for this tranche: %s.',
                        $label,
                        $money ? '₱'.number_format($remaining / 100, 2) : number_format($remaining),
                    ),
                ]);
            }
        }

        $disbursed = $this->payments->disbursedForObligationCents($obligation);

        if ($total < $disbursed) {
            throw ValidationException::withMessages([
                'wages_amount' => sprintf(
                    'The tranche total cannot be less than the ₱%s already disbursed.',
                    number_format($disbursed / 100, 2),
                ),
            ]);
        }
    }

    private function validateDisbursement(ProjectDisbursement $disbursement, array $data): void
    {
        $obligation = ProjectObligation::query()->findOrFail($disbursement->project_obligation_id);
        $amount = $this->payments->amountToCents($data['amount']);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The disbursement amount must be greater than zero.']);
        }

        $otherDisbursed = $obligation->disbursements()
            ->whereKeyNot($disbursement->getKey())
            ->get()
            ->sum(fn (ProjectDisbursement $other): int => $this->payments->amountToCents($other->amount));

        $obligated = $this->payments->obligationCents($obligation);

        if ($otherDisbursed + $amount > $obligated) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Disbursements cannot exceed the tranche obligation. Remaining for this disbursement: ₱%s.',
                    number_format(max(0, $obligated - $otherDisbursed) / 100, 2),
                ),
            ]);
        }

        $duplicate = $obligation->disbursements()
            ->whereKeyNot($disbursement->getKey())
            ->where('ldap_check_number', trim((string) $data['ldap_check_number']))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'ldap_check_number' => 'This LDAP / Check Number is already recorded for the tranche.',
            ]);
        }
    }
}
