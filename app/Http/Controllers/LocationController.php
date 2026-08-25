<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function districts(Province $province): JsonResponse
    {
        return response()->json(
            Municipality::query()
                ->where('province_id', $province->id)
                ->where('is_active', true)
                ->whereNotNull('district')
                ->distinct()
                ->orderBy('district')
                ->pluck('district')
                ->values()
        );
    }

    public function municipalities(Request $request, Province $province): JsonResponse
    {
        return response()->json(
            Municipality::query()
                ->where('province_id', $province->id)
                ->where('is_active', true)
                ->when(
                    $request->filled('district'),
                    fn ($query) => $query->where(
                        'district',
                        $request->string('district')->toString()
                    )
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'district',
                    'income_class',
                    'is_city',
                ])
        );
    }

    public function barangays(Municipality $municipality): JsonResponse
    {
        return response()->json(
            Barangay::query()
                ->where('municipality_id', $municipality->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }
}
