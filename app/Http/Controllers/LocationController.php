<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function municipalities(
        int $province
    ): JsonResponse {
        $municipalities = Municipality::query()
            ->where(
                'province_id',
                $province
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'district',
                'income_class',
            ]);

        return response()->json(
            $municipalities
        );
    }

    public function barangays(
        int $municipality
    ): JsonResponse {
        $barangays = Barangay::query()
            ->where(
                'municipality_id',
                $municipality
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return response()->json(
            $barangays
        );
    }
}