<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdlRealignmentController extends Controller
{
    public function store(
        Request $request,
        Adl $adl
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'not_in:0',
            ],

            'realignment_date' => [
                'required',
                'date',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'reason' => [
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

            $existingRealignments = (float) $lockedAdl
                ->realignments()
                ->sum('amount');

            $allocated = (float) $lockedAdl
                ->allocations()
                ->sum('amount');

            $newAdjustedGrants =
                (float) $lockedAdl->grants
                + $existingRealignments
                + $amount;

            if ($newAdjustedGrants < 0) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'amount' => 'The re-alignment would make the adjusted grant amount negative.',
                    ]);
            }

            if ($newAdjustedGrants < $allocated) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'amount' => sprintf(
                            'This re-alignment would reduce available grants to ₱%s, below the already allocated amount of ₱%s.',
                            number_format(
                                $newAdjustedGrants,
                                2
                            ),
                            number_format(
                                $allocated,
                                2
                            ),
                        ),
                    ]);
            }

            $lockedAdl->realignments()->create([
                'amount' => $amount,
                'realignment_date' => $validated['realignment_date'],
                'reference_number' => $validated['reference_number'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return redirect()
                ->route('adl.show', $lockedAdl)
                ->with(
                    'success',
                    'Re-alignment recorded successfully.'
                );
        });
    }
}