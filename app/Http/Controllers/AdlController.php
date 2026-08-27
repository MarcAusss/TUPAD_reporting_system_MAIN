<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use App\Services\Monitoring\PerAdlSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdlController extends Controller
{
    public function index(): View
    {
        $adls = Adl::query()
            ->withCount(['allocations', 'realignments'])
            ->latest()
            ->paginate(15);

        return view('adl.index', compact('adls'));
    }

    public function create(): View
    {
        return view('adl.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAdl($request);

        $grants = round((float) $validated['grants'], 2);
        $adminCost = round((float) ($validated['admin_cost'] ?? 0), 2);

        /*
        |--------------------------------------------------------------------------
        | Official ADL Total Rule
        |--------------------------------------------------------------------------
        |
        | Total must always be the same amount entered under Grants.
        | Administrative Cost is tracked separately and is NOT added to Total.
        | The server calculates this so a manually manipulated request cannot
        | override the rule.
        |
        */
        $total = $grants;

        $adl = Adl::create([
            'adl_number' => trim($validated['adl_number']),
            'date_received' => $validated['date_received'] ?? null,
            'grants' => $grants,
            'admin_cost' => $adminCost,
            'total' => $total,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('adl.show', $adl)
            ->with('success', 'ADL record created successfully.');
    }

    public function show(Adl $adl, PerAdlSummaryService $summaryService): View
    {
        $adl->load([
            'realignments.creator',
            'allocations.creator',
            'allocations.projects.obligations',
            'allocations.projects.approval',
        ]);

        return view('adl.show', [
            'adl' => $adl,
            'perAdlRows' => $summaryService->rowsForAdl($adl),
        ]);
    }

    public function edit(Adl $adl): View
    {
        return view('adl.edit', compact('adl'));
    }

    public function update(Request $request, Adl $adl): RedirectResponse
    {
        $validated = $this->validateAdl($request, $adl);

        $grants = round((float) $validated['grants'], 2);
        $adminCost = round((float) ($validated['admin_cost'] ?? 0), 2);
        $total = $grants;

        $realignments = (float) $adl->realignments()->sum('amount');
        $newAdjustedGrants = $grants + $realignments;
        $allocated = (float) $adl->allocations()->sum('amount');

        if ($newAdjustedGrants < $allocated) {
            return back()
                ->withInput()
                ->withErrors([
                    'grants' => sprintf(
                        'The adjusted grant would become ₱%s, which is lower than the already allocated amount of ₱%s.',
                        number_format($newAdjustedGrants, 2),
                        number_format($allocated, 2)
                    ),
                ]);
        }

        $adl->update([
            'adl_number' => trim($validated['adl_number']),
            'date_received' => $validated['date_received'] ?? null,
            'grants' => $grants,
            'admin_cost' => $adminCost,
            'total' => $total,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('adl.show', $adl)
            ->with('success', 'ADL record updated successfully.');
    }

    private function validateAdl(Request $request, ?Adl $adl = null): array
    {
        return $request->validate([
            'adl_number' => [
                'required',
                'string',
                'max:100',
                $adl
                    ? Rule::unique('adls', 'adl_number')->ignore($adl->id)
                    : Rule::unique('adls', 'adl_number'),
            ],
            'date_received' => ['nullable', 'date'],
            'grants' => ['required', 'numeric', 'min:0'],
            'admin_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
