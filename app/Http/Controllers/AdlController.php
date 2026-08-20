<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdlController extends Controller
{
    public function index(): View
    {
        $adls = Adl::query()
            ->withCount([
                'allocations',
                'realignments',
            ])
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
        $validated = $request->validate([
            'adl_number' => [
                'required',
                'string',
                'max:100',
                'unique:adls,adl_number',
            ],

            'grants' => [
                'required',
                'numeric',
                'min:0',
            ],

            'admin_cost' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $grants = round(
            (float) $validated['grants'],
            2
        );

        $adminCost = round(
            (float) ($validated['admin_cost'] ?? 0),
            2
        );

        $adl = Adl::create([
            'adl_number' => trim($validated['adl_number']),
            'grants' => $grants,
            'admin_cost' => $adminCost,
            'total' => $grants + $adminCost,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('adl.show', $adl)
            ->with(
                'success',
                'ADL record created successfully.'
            );
    }

    public function show(Adl $adl): View
    {
        $adl->load([
            'realignments.creator',
            'allocations.creator',
        ]);

        return view(
            'adl.show',
            compact('adl')
        );
    }

    public function edit(Adl $adl): View
    {
        return view(
            'adl.edit',
            compact('adl')
        );
    }

    public function update(
        Request $request,
        Adl $adl
    ): RedirectResponse {
        $validated = $request->validate([
            'adl_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('adls', 'adl_number')
                    ->ignore($adl->id),
            ],

            'grants' => [
                'required',
                'numeric',
                'min:0',
            ],

            'admin_cost' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $grants = round(
            (float) $validated['grants'],
            2
        );

        $adminCost = round(
            (float) ($validated['admin_cost'] ?? 0),
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Financial Integrity
        |--------------------------------------------------------------------------
        |
        | Do not allow the adjusted grant amount to become lower than money
        | already allocated.
        |
        */

        $realignments = (float) $adl
            ->realignments()
            ->sum('amount');

        $newAdjustedGrants = $grants + $realignments;

        $allocated = (float) $adl
            ->allocations()
            ->sum('amount');

        if ($newAdjustedGrants < $allocated) {
            return back()
                ->withInput()
                ->withErrors([
                    'grants' => sprintf(
                        'The adjusted grant would become ₱%s, which is lower than the already allocated amount of ₱%s.',
                        number_format($newAdjustedGrants, 2),
                        number_format($allocated, 2),
                    ),
                ]);
        }

        $adl->update([
            'adl_number' => trim($validated['adl_number']),
            'grants' => $grants,
            'admin_cost' => $adminCost,
            'total' => $grants + $adminCost,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('adl.show', $adl)
            ->with(
                'success',
                'ADL record updated successfully.'
            );
    }
}