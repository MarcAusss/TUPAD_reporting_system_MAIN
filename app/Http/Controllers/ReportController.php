<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'province' => [
                'nullable',
                'string',
                'max:150',
            ],

            'municipality' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'string',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ]);

        $query = Project::query()
            ->with([
                'allocation.adl',
                'approval',
            ]);

        if (
            filled(
                $validated['province']
                ?? null
            )
        ) {
            $query->where(
                'province',
                $validated['province']
            );
        }

        if (
            filled(
                $validated['municipality']
                ?? null
            )
        ) {
            $query->where(
                'municipality',
                $validated['municipality']
            );
        }

        if (
            filled(
                $validated['status']
                ?? null
            )
        ) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (
            filled(
                $validated['date_from']
                ?? null
            )
        ) {
            $query->whereDate(
                'date_received',
                '>=',
                $validated['date_from']
            );
        }

        if (
            filled(
                $validated['date_to']
                ?? null
            )
        ) {
            $query->whereDate(
                'date_received',
                '<=',
                $validated['date_to']
            );
        }

        $summaryQuery = clone $query;

        $summary = [
            'projects' =>
                (clone $summaryQuery)->count(),

            'beneficiaries' =>
                (int) (clone $summaryQuery)
                    ->sum('beneficiaries_total'),

            'female_beneficiaries' =>
                (int) (clone $summaryQuery)
                    ->sum('beneficiaries_female'),

            'project_cost' =>
                (float) (clone $summaryQuery)
                    ->sum('total_project_cost'),

            'completed' =>
                (clone $summaryQuery)
                    ->where(
                        'status',
                        ProjectStatus::COMPLETED
                    )
                    ->count(),
        ];

        $projects = $query
            ->latest('date_received')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $provinces = Project::query()
            ->whereNotNull('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');

        $municipalities = Project::query()
            ->when(
                $request->filled('province'),
                fn($query) =>
                $query->where(
                    'province',
                    $request->province
                )
            )
            ->whereNotNull('municipality')
            ->distinct()
            ->orderBy('municipality')
            ->pluck('municipality');

        return view(
            'reports.index',
            [
                'projects' =>
                    $projects,

                'summary' =>
                    $summary,

                'provinces' =>
                    $provinces,

                'municipalities' =>
                    $municipalities,

                'statuses' =>
                    ProjectStatus::cases(),
            ]
        );
    }
}