<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExecutiveDashboardRequest;
use App\Services\Dashboards\ExecutiveDashboardService;
use Illuminate\View\View;

class ExecutiveDashboardController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $dashboard,
    ) {}

    public function index(ExecutiveDashboardRequest $request): View
    {
        return view('executive-dashboard.index', $this->viewData($request));
    }

    public function presentation(ExecutiveDashboardRequest $request): View
    {
        return view(
            'executive-dashboard.presentation',
            $this->viewData($request),
        );
    }

    private function viewData(ExecutiveDashboardRequest $request): array
    {
        $filters = $request->reportFilters();

        return [
            'dashboard' => $this->dashboard->build($filters),
            'options' => $this->dashboard->filterOptions(),
            'query' => $request->filterQuery(),
        ];
    }
}
