<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use App\Services\Monitoring\PerAdlSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FundMonitoringController extends Controller
{
    public function __construct(private readonly PerAdlSummaryService $service) {}

    public function perAdl(Request $request): View
    {
        $rows = $this->filteredRows($request);
        return view('monitoring.per-adl', [
            'rows' => $rows,
            'adls' => Adl::query()->orderBy('adl_number')->get(['id', 'adl_number']),
        ]);
    }

    public function summary(Request $request): View
    {
        return $this->summaryView($request, 'SUMMARY', false);
    }

    public function summaryCurrent(Request $request): View
    {
        return $this->summaryView($request, 'SUMMARY (Current)', true);
    }

    public function perProvince(Request $request): View
    {
        $rows = $this->filteredRows($request);
        $provinces = $rows->groupBy(fn (array $row) => $row['province'] ?: 'Unassigned')
            ->map(fn (Collection $items, string $province) => $this->aggregate($items) + ['province' => $province])
            ->sortBy('province')
            ->values();

        return view('monitoring.per-province', compact('provinces'));
    }

    private function summaryView(Request $request, string $title, bool $current): View
    {
        $rows = $this->filteredRows($request);
        return view('monitoring.summary', [
            'title' => $title,
            'current' => $current,
            'totals' => $this->aggregate($rows),
            'provinceCount' => $rows->pluck('province')->filter()->unique()->count(),
            'rowCount' => $rows->count(),
        ]);
    }

    private function filteredRows(Request $request): Collection
    {
        $rows = $this->service->allRows();
        if ($request->filled('adl_id')) {
            $rows = $rows->where('adl_id', (int) $request->integer('adl_id'));
        }
        if ($request->filled('province')) {
            $rows = $rows->filter(fn (array $row) => strcasecmp((string) $row['province'], (string) $request->string('province')) === 0);
        }
        return $rows->values();
    }

    private function aggregate(Collection $rows): array
    {
        $sum = fn (string $key): float => (float) $rows->sum($key);
        $target = $sum('target_grants');
        $obligated = $sum('obligated_grants');

        return [
            'allocation_grants' => $sum('allocation_grants'),
            'allocation_admin_cost' => $sum('allocation_admin_cost'),
            'allocation_total' => $sum('allocation_total'),
            'realignment_grants' => $sum('realignment_grants'),
            'target_grants' => $target,
            'target_beneficiaries' => (int) $rows->sum('target_beneficiaries'),
            'obligated_grants' => $obligated,
            'utilization' => $target > 0 ? ($obligated / $target) * 100 : 0,
            'wages' => $sum('wages'), 'ppe' => $sum('ppe'), 'insurance' => $sum('insurance'),
            'beneficiaries' => (int) $rows->sum('beneficiaries'), 'female' => (int) $rows->sum('female'),
            'unutilized' => $sum('unutilized'), 'for_payment' => $sum('for_payment'),
            'post_docs' => $sum('post_docs'), 'ongoing_implementation' => $sum('ongoing_implementation'),
            'for_implementation' => $sum('for_implementation'), 'approved' => $sum('approved'),
            'for_approval' => $sum('for_approval'), 'under_evaluation' => $sum('under_evaluation'),
            'available_balance' => $sum('available_balance'), 'remaining' => $sum('remaining'), 'unused' => $sum('unused'),
        ];
    }
}
