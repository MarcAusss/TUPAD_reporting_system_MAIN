<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdlAllocationController extends Controller
{
    public function store(Request $request, Adl $adl): RedirectResponse
    {
        $validated = $request->validate([
            'fund_sponsor' => ['required', 'string', 'max:255'],
            'partner' => ['required', 'string', 'max:255'],
            'local_chief_executive_partylist' => ['nullable', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'grant_amount' => ['required', 'numeric', 'gt:0'],
            'admin_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $grants = round((float) $validated['grant_amount'], 2);
        $admin = round((float) ($validated['admin_cost_amount'] ?? 0), 2);
        $total = $grants + $admin;

        return DB::transaction(function () use ($request, $validated, $total, $grants, $admin, $adl) {
            $lockedAdl = Adl::query()->lockForUpdate()->findOrFail($adl->id);
            $adjustedGrants = (float) $lockedAdl->grants + (float) $lockedAdl->realignments()->sum('amount');
            $allocated = (float) $lockedAdl->allocations()->sum('amount');
            $remaining = $adjustedGrants - $allocated;

            if ($grants > $remaining) {
                return back()->withInput()->withErrors([
                    'grant_amount' => sprintf('Grant allocation exceeds the remaining grant balance. Available grants: ₱%s.', number_format($remaining, 2)),
                ]);
            }

            $lockedAdl->allocations()->create([
                'fund_sponsor' => trim($validated['fund_sponsor']),
                'partner' => trim($validated['partner']),
                'local_chief_executive_partylist' => $validated['local_chief_executive_partylist'] ?? null,
                'location' => trim($validated['location']),
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'municipality' => $validated['municipality'] ?? null,
                // Keep the legacy amount column as the grant amount for compatibility with existing project budget logic.
                'amount' => $grants,
                'grant_amount' => $grants,
                'admin_cost_amount' => $admin,
                'total_amount' => $total,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return redirect()->route('adl.show', $lockedAdl)->with('success', 'Fund allocation recorded successfully.');
        });
    }
}
