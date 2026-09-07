<?php

namespace App\Http\Controllers;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ProjectInterventionFocus;
use App\Services\Dashboards\DashboardGeographicAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardGeographicAnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardGeographicAnalyticsService $analytics,
    ): JsonResponse {
        $validated = $request->validate([
            'family' => ['required', Rule::in(DashboardGeographicAnalyticsService::families())],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'sector_group' => [
                'nullable',
                Rule::in([
                    BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                    BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD,
                ]),
            ],
            'sector' => ['nullable', Rule::enum(BeneficiarySectorCategory::class)],
            'intervention_focus' => ['nullable', Rule::enum(ProjectInterventionFocus::class)],
        ]);

        abort_unless($request->user()?->isFocal(), 403);

        return response()->json($analytics->build($request->user(), $validated));
    }
}
