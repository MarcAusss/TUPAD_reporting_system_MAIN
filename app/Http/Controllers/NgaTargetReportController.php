<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NgaTargetReportController extends Controller
{
    /**
     * Official Bicol province order used when the report is categorized
     * per Agency (Partner) — any province outside this list sorts last.
     */
    private const PROVINCE_ORDER = [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ];

    /**
     * Read-only NGA Target Accomplishment report, one row per project.
     *
     * Column groups (three, each split into Beneficiaries / Amount), mirroring
     * the Physical & Financial "Overall Accomplishment" report's convention:
     * - Target: the project's own declared beneficiaries / total cost.
     * - Accomplished: what has actually been paid — summed disbursements
     *   (Direct Administration) or the ACP payment (Through ACP). Accomplished
     *   beneficiaries has no separate per-payment count on record, so it
     *   mirrors Target beneficiaries once any amount has actually been paid.
     * - Balance: Target minus Accomplished (the remainder still owed).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $agencyFilter = trim((string) $request->string('agency'));
        $programFilter = trim((string) $request->string('program'));

        $agencyOptions = Project::query()
            ->whereNotNull('partner')
            ->where('partner', '!=', '')
            ->distinct()
            ->orderBy('partner')
            ->pluck('partner');

        $programOptions = Project::query()
            ->whereNotNull('program')
            ->where('program', '!=', '')
            ->distinct()
            ->orderBy('program')
            ->pluck('program');

        $projects = Project::query()
            ->with([
                'projectLocations.province',
                'projectLocations.municipality',
                'provinceReference',
                'municipalityReference',
                'obligations.disbursements',
                'acpPayment',
                'payout',
            ])
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($inner) use ($search) {
                    $inner->where('partner', 'like', "%{$search}%")
                        ->orWhere('program', 'like', "%{$search}%")
                        ->orWhere('municipality', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%");
                }),
            )
            ->when($agencyFilter !== '', fn ($query) => $query->where('partner', $agencyFilter))
            ->when($programFilter !== '', fn ($query) => $query->where('program', $programFilter))
            ->get();

        $rows = $projects
            ->map(fn (Project $project) => $this->buildRow($project))
            ->sort(
                fn (array $a, array $b) => [
                    strtolower($a['agency']),
                    $this->provinceRank($a['province']),
                    strtolower($a['municipality']),
                ] <=> [
                    strtolower($b['agency']),
                    $this->provinceRank($b['province']),
                    strtolower($b['municipality']),
                ]
            )
            ->values();

        $totals = $this->sumRows($rows);

        return view('reports.nga-targets.index', [
            'rows' => $rows,
            'totals' => $totals,
            'search' => $search,
            'agencyFilter' => $agencyFilter,
            'programFilter' => $programFilter,
            'agencyOptions' => $agencyOptions,
            'programOptions' => $programOptions,
            'projectCount' => $projects->count(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sumRows($rows): array
    {
        return [
            'target_beneficiaries' => (int) $rows->sum('target.beneficiaries'),
            'target_amount' => (float) $rows->sum('target.amount'),
            'accomplished_beneficiaries' => (int) $rows->sum('accomplished.beneficiaries'),
            'accomplished_amount' => (float) $rows->sum('accomplished.amount'),
            'balance_beneficiaries' => (int) $rows->sum('balance.beneficiaries'),
            'balance_amount' => (float) $rows->sum('balance.amount'),
        ];
    }

    private function provinceRank(string $province): int
    {
        $index = array_search($province, self::PROVINCE_ORDER, true);

        return $index === false ? count(self::PROVINCE_ORDER) : $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(Project $project): array
    {
        [$municipality, $province] = $this->resolveLocation($project);

        $targetBeneficiaries = (int) $project->beneficiaries_total;
        $targetAmount = (float) $project->total_project_cost;

        $accomplishedAmount = $project->implementation_mode === ImplementationMode::THROUGH_ACP
            ? (float) ($project->acpPayment?->amount ?? 0)
            : (float) $project->obligations
                ->flatMap(fn ($obligation) => $obligation->disbursements)
                ->sum('amount');

        // Actual per-beneficiary payout counts are not tracked separately —
        // once any amount has actually been paid, all target beneficiaries
        // for the project are treated as accomplished.
        $accomplishedBeneficiaries = $accomplishedAmount > 0 ? $targetBeneficiaries : 0;

        return [
            'project_id' => $project->id,
            'agency' => $project->partner ?: '—',
            'program' => $project->program ?: '—',
            'municipality' => $municipality,
            'province' => $province,
            'number_of_days' => (int) $project->number_of_days,

            'target' => [
                'beneficiaries' => $targetBeneficiaries,
                'amount' => $targetAmount,
            ],

            'accomplished' => [
                'beneficiaries' => $accomplishedBeneficiaries,
                'amount' => $accomplishedAmount,
            ],

            'balance' => [
                'beneficiaries' => $targetBeneficiaries - $accomplishedBeneficiaries,
                'amount' => $targetAmount - $accomplishedAmount,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveLocation(Project $project): array
    {
        if ($project->projectLocations->isNotEmpty()) {
            $municipalities = $project->projectLocations
                ->pluck('municipality.name')
                ->filter()
                ->unique()
                ->implode(', ');

            $province = $project->projectLocations->first()->province?->name;

            return [$municipalities ?: '—', $province ?: '—'];
        }

        if ($project->municipalityReference && $project->provinceReference) {
            return [$project->municipalityReference->name, $project->provinceReference->name];
        }

        return [$project->municipality ?: '—', $project->province ?: '—'];
    }
}
