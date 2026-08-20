<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdlAllocationController extends Controller
{
    public function store(
        Request $request,
        Adl $adl
    ): RedirectResponse {
        $validated = $request->validate([
            'fund_sponsor' => [
                'required',
                'string',
                'max:255',
            ],

            'partner' => [
                'required',
                'string',
                'max:255',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $amount = round(
            (float) $validated['amount'],
            2
        );

        return DB::transaction(function () use (
            $request,
            $validated,
            $amount,
            $adl
        ) {
            $lockedAdl = Adl::query()
                ->lockForUpdate()
                ->findOrFail($adl->id);

            $realignments = (float) $lockedAdl
                ->realignments()
                ->sum('amount');

            $adjustedGrants =
                (float) $lockedAdl->grants
                + $realignments;

            $allocated = (float) $lockedAdl
                ->allocations()
                ->sum('amount');

            $remaining =
                $adjustedGrants
                - $allocated;

            if ($amount > $remaining) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'amount' => sprintf(
                            'Allocation exceeds the remaining grant balance. Available amount: ₱%s.',
                            number_format(
                                $remaining,
                                2
                            ),
                        ),
                    ]);
            }

            $lockedAdl->allocations()->create([
                'fund_sponsor' => trim($validated['fund_sponsor']),
                'partner' => trim($validated['partner']),
                'location' => trim($validated['location']),
                'amount' => $amount,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return redirect()
                ->route('adl.show', $lockedAdl)
                ->with(
                    'success',
                    'Fund allocation recorded successfully.'
                );
        });
    }
}