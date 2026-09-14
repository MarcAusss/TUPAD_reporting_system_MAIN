<?php

namespace App\Http\Controllers;

use App\Models\NgaTarget;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NgaTargetReportController extends Controller
{
    /**
     * Read-only Target / Accomplished / Balance report per NGA, styled after
     * the existing Physical & Financial Accomplishment (Overall Accomplishment)
     * report. Source data is the same nga_targets table the Targets
     * management screen maintains.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $targets = NgaTarget::query()
            ->when(
                $search !== '',
                fn ($query) => $query->where('nga', 'like', "%{$search}%"),
            )
            ->orderBy('nga')
            ->get();

        $totals = [
            'total_beneficiaries' => (int) $targets->sum('total_beneficiaries'),
            'amount' => (float) $targets->sum('amount'),
            'accomplishments' => (int) $targets->sum('accomplishments'),
            'balance' => (int) $targets->sum('balance'),
        ];

        return view('reports.nga-targets.index', [
            'targets' => $targets,
            'totals' => $totals,
            'search' => $search,
        ]);
    }
}
