<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
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
            'description' => 'Consolidated physical and financial accomplishment for the selected reporting scope.',
            'dimension' => ReportDimension::OVERALL,
            'term' => null,
        ],
        'quarter' => [
            'label' => 'Accomplishment per Quarter',
            'short_label' => 'Per Quarter',
            'description' => 'Quarter-by-quarter accomplishment using the project date received as the reporting-period basis.',
            'dimension' => ReportDimension::QUARTER,
            'term' => null,
        ],
        'month' => [
            'label' => 'Accomplishment per Month',
            'short_label' => 'Per Month',
            'description' => 'Month-by-month accomplishment using the project date received as the reporting-period basis.',
            'dimension' => ReportDimension::MONTH,
            'term' => null,
        ],
        'short-term' => [
            'label' => 'Short-Term Accomplishment',
            'short_label' => 'Short-Term',
            'description' => 'Accomplishment for projects classified as Short-Term based on the authoritative project term.',
            'dimension' => ReportDimension::OVERALL,
            'term' => ProjectTerm::SHORT_TERM,
        ],
        'long-term' => [
            'label' => 'Long-Term Accomplishment',
            'short_label' => 'Long-Term',
            'description' => 'Accomplishment for projects classified as Long-Term based on the authoritative project term.',
            'dimension' => ReportDimension::OVERALL,
            'term' => ProjectTerm::LONG_TERM,
        ],
    ];

    public function __construct(
        private readonly ReportGenerationService $reports,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function index(Request $request): View
    {
        $viewKey = array_key_exists((string) $request->query('view'), self::VIEWS)
            ? (string) $request->query('view')
            : 'overall';

        $view = self::VIEWS[$viewKey];
        $input = $request->query();

        if (in_array($viewKey, ['quarter', 'month'], true) && blank($input['fiscal_year'] ?? null)) {
            $input['fiscal_year'] = now('Asia/Manila')->year;
        }

        if ($viewKey !== 'quarter') {
            unset($input['quarter']);
        }

        if ($viewKey !== 'month') {
            unset($input['month']);
        }

        $validated = validator($input, [
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100', 'required_with:quarter,month'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ])->after(function ($validator) use ($input): void {
            $implementationMode = ImplementationMode::tryFrom(
                (string) ($input['implementation_mode'] ?? '')
            );
            $status = ProjectStatus::tryFrom((string) ($input['status'] ?? ''));

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

        $validated['term'] = $view['term']?->value;

        $filters = ReportFilters::fromArray($validated);
        $report = $this->reports->generate(
            ReportType::PHYSICAL_FINANCIAL,
            $view['dimension'],
            $filters,
        );
        $overall = $this->reports->generate(
            ReportType::PHYSICAL_FINANCIAL,
            ReportDimension::OVERALL,
            $filters,
        );
        $statusReport = $this->reports->generate(
            ReportType::PHYSICAL_FINANCIAL,
            ReportDimension::STATUS,
            $filters,
        );

        $overallRow = $overall['rows']->first() ?? [];
        $projects = (int) ($overallRow['project_count'] ?? 0);
        $completed = (int) ($overallRow['completed_project_count'] ?? 0);
        $beneficiaries = (int) ($overallRow['beneficiaries_total'] ?? 0);
        $female = (int) ($overallRow['beneficiaries_female'] ?? 0);
        $obligated = (int) ($overallRow['obligated_cents'] ?? 0);
        $disbursed = (int) ($overallRow['disbursed_cents'] ?? 0);

        $exportQuery = array_filter([
            'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
            'group_by' => $view['dimension']->value,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'quarter' => $validated['quarter'] ?? null,
            'month' => $validated['month'] ?? null,
            'term' => $validated['term'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $commonQuery = array_filter([
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $user = $request->user();

        return view('reports.physical-financial.index', [
            'viewKey' => $viewKey,
            'viewConfig' => $view,
            'views' => self::VIEWS,
            'report' => $report,
            'overall' => $overall,
            'statusReport' => $statusReport,
            'overallRow' => $overallRow,
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
            'ratios' => [
                'completion' => $projects > 0 ? round(($completed / $projects) * 100, 1) : 0.0,
                'female_share' => $beneficiaries > 0 ? round(($female / $beneficiaries) * 100, 1) : 0.0,
                'disbursement_vs_obligation' => $obligated > 0 ? round(($disbursed / $obligated) * 100, 1) : 0.0,
            ],
        ]);
    }
}
