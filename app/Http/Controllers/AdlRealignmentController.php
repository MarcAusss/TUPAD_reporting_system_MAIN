<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdlRealignmentController extends Controller
{
    public function store(Request $request, Adl $adl): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'realignment_date' => ['required', 'date'],
            'maf_date' => ['nullable', 'date'],
            'maf_number' => ['nullable', 'string', 'max:150'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $amount = round((float) $validated['amount'], 2);

        return DB::transaction(function () use ($request, $validated, $amount, $adl) {
            $lockedAdl = Adl::query()->lockForUpdate()->findOrFail($adl->id);
            $existing = (float) $lockedAdl->realignments()->sum('amount');
            $allocated = (float) $lockedAdl->allocations()->sum('amount');
            $newAdjustedGrants = (float) $lockedAdl->grants + $existing + $amount;

            if ($newAdjustedGrants < 0 || $newAdjustedGrants < $allocated) {
                return back()->withInput()->withErrors([
                    'amount' => sprintf('This re-alignment would reduce adjusted grants to ₱%s, below existing allocations of ₱%s.', number_format($newAdjustedGrants, 2), number_format($allocated, 2)),
                ]);
            }

            $lockedAdl->realignments()->create([
                'amount' => $amount,
                'realignment_date' => $validated['realignment_date'],
                'maf_date' => $validated['maf_date'] ?? $validated['realignment_date'],
                'maf_number' => $validated['maf_number'] ?? ($validated['reference_number'] ?? null),
                'reference_number' => $validated['reference_number'] ?? ($validated['maf_number'] ?? null),
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return redirect()->route('adl.show', $lockedAdl)->with('success', 'Re-alignment recorded successfully.');
        });
    }
}
