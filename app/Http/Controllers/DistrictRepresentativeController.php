<?php

namespace App\Http\Controllers;

use App\Models\DistrictRepresentative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DistrictRepresentativeController extends Controller
{
    /**
     * Focal-only: standalone editor (Administration → District Representatives).
     */
    public function edit(): View
    {
        return view('reports.district-representatives.edit', [
            'representatives' => DistrictRepresentative::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /**
     * Focal-only: replace the representative names shown on the Summary per
     * District report (updated after each congressional election).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'representatives' => ['required', 'array', 'min:1'],
            'representatives.*.id' => [
                'required',
                'integer',
                'distinct',
                'exists:district_representatives,id',
            ],
            'representatives.*.representative' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;

        DB::transaction(function () use ($validated, $userId): void {
            foreach ($validated['representatives'] as $entry) {
                $representative = DistrictRepresentative::query()->findOrFail($entry['id']);

                $representative->fill([
                    'representative' => filled($entry['representative'] ?? null)
                        ? trim($entry['representative'])
                        : null,
                ]);

                if ($representative->isDirty()) {
                    $representative->updated_by = $userId;
                    $representative->save();
                }
            }
        });

        // Saved from the Administration page: stay there.
        if ($request->input('return_to') === 'admin') {
            return redirect()
                ->route('reports.district-representatives.edit')
                ->with('success', 'District representatives updated.');
        }

        return redirect()
            ->route('reports.workspace.fund-status', array_merge(
                $request->only(['fiscal_year', 'status', 'implementation_mode', 'province_id']),
                ['view' => 'district'],
            ))
            ->with('success', 'District representatives updated.');
    }
}
