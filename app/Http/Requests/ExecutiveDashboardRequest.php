<?php

namespace App\Http\Requests;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Reports\ReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExecutiveDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fiscal_year' => [
                'nullable',
                'integer',
                'between:2000,2100',
                'required_with:quarter,month',
            ],
            'quarter' => [
                'nullable',
                'integer',
                'between:1,4',
            ],
            'month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
            'term' => ['nullable', Rule::enum(ProjectTerm::class)],
            'adl_id' => ['nullable', 'integer', 'exists:adls,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'municipality_id' => [
                'nullable',
                'integer',
                'exists:municipalities,id',
            ],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'sponsor' => ['nullable', 'string', 'max:255'],
            'partner' => ['nullable', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:255'],
            'sector' => [
                'nullable',
                Rule::enum(BeneficiarySectorCategory::class),
            ],
            'intervention_focus' => [
                'nullable',
                Rule::enum(ProjectInterventionFocus::class),
            ],
            'labor_market_program' => [
                'nullable',
                Rule::enum(LaborMarketProgram::class),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('quarter') && $this->filled('month')) {
                    $validator->errors()->add(
                        'quarter',
                        'Quarter and month cannot be used at the same time.'
                    );
                    $validator->errors()->add(
                        'month',
                        'Month and quarter cannot be used at the same time.'
                    );
                }

                $provinceId = $this->integer('province_id');
                $municipalityId = $this->integer('municipality_id');
                $barangayId = $this->integer('barangay_id');
                $district = trim((string) $this->input('district', ''));

                if ($provinceId && $municipalityId) {
                    $valid = Municipality::query()
                        ->whereKey($municipalityId)
                        ->where('province_id', $provinceId)
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'municipality_id',
                            'The selected municipality does not belong to the selected province.'
                        );
                    }
                }

                if ($municipalityId && $barangayId) {
                    $valid = Barangay::query()
                        ->whereKey($barangayId)
                        ->where('municipality_id', $municipalityId)
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'barangay_id',
                            'The selected barangay does not belong to the selected municipality.'
                        );
                    }
                }

                if ($provinceId && $barangayId) {
                    $valid = Barangay::query()
                        ->whereKey($barangayId)
                        ->whereHas(
                            'municipality',
                            fn ($query) => $query->where('province_id', $provinceId),
                        )
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'barangay_id',
                            'The selected barangay does not belong to the selected province.'
                        );
                    }
                }

                if ($district !== '' && $municipalityId) {
                    $valid = Municipality::query()
                        ->whereKey($municipalityId)
                        ->where('district', $district)
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'district',
                            'The selected district does not match the selected municipality.'
                        );
                    }
                }

                if ($district !== '' && $barangayId) {
                    $valid = Barangay::query()
                        ->whereKey($barangayId)
                        ->whereHas(
                            'municipality',
                            fn ($query) => $query->where('district', $district),
                        )
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'barangay_id',
                            'The selected barangay does not belong to the selected district.'
                        );
                    }
                }

                if ($district !== '' && $provinceId && ! $municipalityId) {
                    $valid = Municipality::query()
                        ->where('province_id', $provinceId)
                        ->where('district', $district)
                        ->exists()
                        || ProjectLocation::query()
                            ->where('province_id', $provinceId)
                            ->where('district', $district)
                            ->exists()
                        || Project::query()
                            ->where('province_id', $provinceId)
                            ->where('district', $district)
                            ->exists();

                    if (! $valid) {
                        $validator->errors()->add(
                            'district',
                            'The selected district is not available for the selected province.'
                        );
                    }
                }

                $implementationMode = ImplementationMode::tryFrom(
                    (string) $this->input('implementation_mode', '')
                );
                $status = ProjectStatus::tryFrom((string) $this->input('status', ''));

                if (
                    $implementationMode === ImplementationMode::DIRECT_ADMINISTRATION
                    && in_array($status, [
                        ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                        ProjectStatus::FOR_LIQUIDATION,
                        ProjectStatus::PARTIALLY_LIQUIDATED,
                    ], true)
                ) {
                    $validator->errors()->add(
                        'status',
                        'The selected status belongs only to Through ACP projects.'
                    );
                }

                if (
                    $implementationMode === ImplementationMode::THROUGH_ACP
                    && $status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
                ) {
                    $validator->errors()->add(
                        'status',
                        'For Submission of Post-Docs belongs only to Direct Administration projects.'
                    );
                }
            },
        ];
    }

    public function reportFilters(): ReportFilters
    {
        return ReportFilters::fromArray($this->validated());
    }

    public function filterQuery(): array
    {
        return collect($this->validated())
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }
}
