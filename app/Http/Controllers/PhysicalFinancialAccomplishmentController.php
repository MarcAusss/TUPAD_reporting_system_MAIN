<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PhysicalFinancialAccomplishmentController extends Controller
{
    private const VIEWS = [
        'overall' => [
            'label' => 'Overall Accomplishment',
            'short_label' => 'Overall',
            'description' => 'Province-level reformulated target, accomplishment, and balance in physical and financial terms.',
            'dimension' => ReportDimension::OVERALL,
        ],
        'semester' => [
            'label' => 'Accomplishment per Semester',
            'short_label' => 'Per Semester',
            'description' => 'Province-level first- and second-semester physical and financial accomplishment for the selected fiscal year.',
            'dimension' => ReportDimension::SEMESTER,
        ],
        'quarter' => [
            'label' => 'Accomplishment per Quarter',
            'short_label' => 'Per Quarter',
            'description' => 'Province-level first through fourth quarter physical and financial accomplishment for the selected fiscal year.',
            'dimension' => ReportDimension::QUARTER,
        ],
        'month' => [
            'label' => 'Accomplishment per Month',
            'short_label' => 'Per Month',
            'description' => 'Province-level January through December physical and financial accomplishment for the selected fiscal year.',
            'dimension' => ReportDimension::MONTH,
        ],
    ];

    public function __construct(
        private readonly ReportGenerationService $reports,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function index(Request $request): View
    {
        $viewKey = array_key_exists(
            (string) $request->query('view'),
            self::VIEWS,
        )
            ? (string) $request->query('view')
            : 'overall';

        $view = self::VIEWS[$viewKey];
        $input = $request->query();

        unset(
            $input['term'],
            $input['quarter'],
            $input['month'],
        );

        if (
            in_array($viewKey, ['semester', 'quarter', 'month'], true)
            && blank($input['fiscal_year'] ?? null)
        ) {
            $input['fiscal_year'] = now('Asia/Manila')->year;
        }

        $validated = validator($input, [
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => [
                'nullable',
                Rule::enum(ImplementationMode::class),
            ],
            'province_id' => [
                'nullable',
                'integer',
                'exists:provinces,id',
            ],
        ])->after(function ($validator) use ($input): void {
            $implementationMode = ImplementationMode::tryFrom(
                (string) ($input['implementation_mode'] ?? '')
            );
            $status = ProjectStatus::tryFrom(
                (string) ($input['status'] ?? '')
            );

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
        })->validate();

        $filters = ReportFilters::fromArray($validated);
        $report = $this->reports->generate(
            ReportType::PHYSICAL_FINANCIAL,
            $view['dimension'],
            $filters,
        );

        $matrix = $report['physical_financial_matrix'];
        $matrixTotal = $matrix['total'];

        $exportQuery = array_filter([
            'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
            'group_by' => $view['dimension']->value,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' =>
                $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
        ], static fn (mixed $value): bool =>
            $value !== null && $value !== '');

        $commonQuery = array_filter([
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' =>
                $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
        ], static fn (mixed $value): bool =>
            $value !== null && $value !== '');

        $user = $request->user();

        return view('reports.physical-financial.index', [
            'viewKey' => $viewKey,
            'viewConfig' => $view,
            'views' => self::VIEWS,
            'report' => $report,
            'matrix' => $matrix,
            'matrixTotal' => $matrixTotal,
            'exportQuery' => $exportQuery,
            'commonQuery' => $commonQuery,
            'filters' => $validated,
            'options' => [
                'statuses' => ProjectStatus::cases(),
                'implementation_modes' => ImplementationMode::cases(),
                'provinces' => $this->provinceAccess
                    ->scopeProvinces(Province::query(), $user)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'provinceLocked' => $user->isTc(),
        ]);
    }
}
