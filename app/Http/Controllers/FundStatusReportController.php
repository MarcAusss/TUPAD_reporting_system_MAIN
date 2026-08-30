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

class FundStatusReportController extends Controller
{
    private const VIEWS = [
        'utilization' => [
            'label' => 'Fund Utilization Report',
            'description' => 'TUPAD allocation, accomplishment (obligated), and unobligated balance for the selected reporting scope.',
            'dimension' => ReportDimension::OVERALL,
        ],
        'adl' => [
            'label' => 'Report ADL',
            'description' => 'Fund utilization grouped by Advice of Disbursement Limit (ADL).',
            'dimension' => ReportDimension::ADL,
        ],
        'province' => [
            'label' => 'Report Province',
            'description' => 'Fund status grouped by official project province.',
            'dimension' => ReportDimension::PROVINCE,
        ],
        'status' => [
            'label' => 'Report Status',
            'description' => 'Fund status grouped by the consolidated project workflow status.',
            'dimension' => ReportDimension::STATUS,
        ],
        'sponsor' => [
            'label' => 'Report Sponsor',
            'description' => 'Fund status grouped by the encoded project fund sponsor.',
            'dimension' => ReportDimension::SPONSOR,
        ],
        'nga' => [
            'label' => 'Report NGA',
            'description' => 'Fund status grouped by the existing Partner / NGA project reference.',
            'dimension' => ReportDimension::PARTNER,
        ],
        'district' => [
            'label' => 'Report District',
            'description' => 'District project cohort. Financial amounts are not fabricated where no exact district allocation exists.',
            'dimension' => ReportDimension::DISTRICT,
        ],
        'lce' => [
            'label' => 'Report LCE',
            'description' => 'Fund status grouped by the authoritative Local Chief Executive / Party-list reference on the ADL allocation.',
            'dimension' => ReportDimension::LCE,
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
            : 'utilization';
        $view = self::VIEWS[$viewKey];

        $validated = validator($request->query(), [
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ])->after(function ($validator) use ($request): void {
            $implementationMode = ImplementationMode::tryFrom(
                (string) $request->query('implementation_mode', '')
            );
            $status = ProjectStatus::tryFrom((string) $request->query('status', ''));

            if (
                $implementationMode === ImplementationMode::DIRECT_ADMINISTRATION
                && in_array($status, [
                    ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                    ProjectStatus::FOR_LIQUIDATION,
                    ProjectStatus::PARTIALLY_LIQUIDATED,
                ], true)
            ) {
                $validator->errors()->add('status', 'The selected status belongs only to Through ACP projects.');
            }

            if (
                $implementationMode === ImplementationMode::THROUGH_ACP
                && $status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
            ) {
                $validator->errors()->add('status', 'For Submission of Post-Docs belongs only to Direct Administration projects.');
            }
        })->validate();

        $filters = ReportFilters::fromArray($validated);
        $report = $this->reports->generate(ReportType::FUND_STATUS, $view['dimension'], $filters);
        $overall = $this->reports->generate(ReportType::FUND_STATUS, ReportDimension::OVERALL, $filters);
        $overallRow = $overall['rows']->first() ?? [];

        $exportQuery = array_filter([
            'report_type' => ReportType::FUND_STATUS->value,
            'group_by' => $view['dimension']->value,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
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
        $allocation = (int) ($overallRow['allocation_cents'] ?? 0);
        $obligated = (int) ($overallRow['obligated_cents'] ?? 0);
        $disbursed = (int) ($overallRow['disbursed_cents'] ?? 0);

        return view('reports.fund-status.index', [
            'viewKey' => $viewKey,
            'viewConfig' => $view,
            'views' => self::VIEWS,
            'report' => $report,
            'overall' => $overall,
            'overallRow' => $overallRow,
            'filters' => $validated,
            'exportQuery' => $exportQuery,
            'commonQuery' => $commonQuery,
            'provinceLocked' => $user->isTc(),
            'options' => [
                'statuses' => ProjectStatus::cases(),
                'implementation_modes' => ImplementationMode::cases(),
                'provinces' => $this->provinceAccess
                    ->scopeProvinces(Province::query(), $user)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
            'utilization' => [
                'obligation_rate' => $allocation > 0 ? round(($obligated / $allocation) * 100, 1) : 0.0,
                'disbursement_rate' => $allocation > 0 ? round(($disbursed / $allocation) * 100, 1) : 0.0,
            ],
        ]);
    }
}
