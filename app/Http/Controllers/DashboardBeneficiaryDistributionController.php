<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Dashboards\DashboardBeneficiaryDistributionService;
use App\Services\Dashboards\DashboardProjectDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Barangay drill-down for the Focal and TUPAD Coordinator dashboard beneficiary distribution. */
class DashboardBeneficiaryDistributionController extends Controller
{
    public function barangays(
        Request $request,
        DashboardBeneficiaryDistributionService $distribution,
        ProvinceAccessService $provinceAccess,
    ): JsonResponse {
        $validated = $request->validate([
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
        ]);

        $user = $request->user();
        $municipality = Municipality::query()->findOrFail($validated['municipality_id']);

        // Focal sees the whole region; a TUPAD Coordinator only their province.
        abort_unless(
            $user?->isFocal() || ($user?->isTc() && $provinceAccess->canAccessProvince($user, (int) $municipality->province_id)),
            403,
        );

        return response()->json($distribution->barangays($municipality));
    }

    /** Barangay drill-down of the project distribution (projects per barangay). */
    public function projectBarangays(
        Request $request,
        DashboardProjectDistributionService $projects,
        ProvinceAccessService $provinceAccess,
    ): JsonResponse {
        $validated = $request->validate([
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
        ]);

        $user = $request->user();
        $municipality = Municipality::query()->findOrFail($validated['municipality_id']);

        abort_unless(
            $user?->isFocal() || ($user?->isTc() && $provinceAccess->canAccessProvince($user, (int) $municipality->province_id)),
            403,
        );

        return response()->json($projects->barangays($user, $municipality));
    }
}
