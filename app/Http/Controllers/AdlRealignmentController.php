<?php

namespace App\Http\Controllers;

use App\Models\Adl;
use App\Models\AdlRealignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdlRealignmentController extends Controller
{
    public function store(
        Request $request,
        Adl $adl
    ): RedirectResponse {
        $validated = $request->validate([
            'direction' => [
                'required',
                Rule::in([
                    AdlRealignment::DIRECTION_TUPAD_TO_GIP,
                    AdlRealignment::DIRECTION_GIP_TO_TUPAD,
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Amount Entered by Focal
            |--------------------------------------------------------------------------
            |
            | The user always enters a positive amount. The selected direction
            | determines whether the stored financial effect is negative or
            | positive.
            |
            */

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'realignment_date' => [
                'required',
                'date',
            ],

            'maf_date' => [
                'nullable',
                'date',
            ],

            'maf_number' => [
                'nullable',
                'string',
                'max:150',
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

        $enteredAmount = round(
            (float) $validated['amount'],
            2
        );

        $direction = $validated['direction'];

        /*
        |--------------------------------------------------------------------------
        | Realignment Formula
        |--------------------------------------------------------------------------
        |
        | TUPAD to GIP:
        |   adjusted TUPAD amount = current amount - entered amount
        |
        | GIP to TUPAD:
        |   adjusted TUPAD amount = current amount + entered amount
        |
        | The database keeps the financial effect as a signed amount so the
        | existing SUM(amount) calculations continue to remain authoritative.
        |
        */

        $signedAmount =
            $direction
                === AdlRealignment::DIRECTION_TUPAD_TO_GIP
                    ? -$enteredAmount
                    : $enteredAmount;

        return DB::transaction(
            function () use (
                $request,
                $validated,
                $direction,
                $enteredAmount,
                $signedAmount,
                $adl
            ) {
                $lockedAdl = Adl::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $adl->id
                    );

                $existingRealignment =
                    (float) $lockedAdl
                        ->realignments()
                        ->sum('amount');

                $allocated =
                    (float) $lockedAdl
                        ->allocations()
                        ->sum('amount');

                $currentAdjustedGrants =
                    (float) $lockedAdl->grants
                    + $existingRealignment;

                $newAdjustedGrants =
                    $currentAdjustedGrants
                    + $signedAmount;

                /*
                |--------------------------------------------------------------------------
                | TUPAD to GIP Protection
                |--------------------------------------------------------------------------
                |
                | A deduction cannot:
                | - make the TUPAD ADL amount negative; or
                | - reduce the fund below grants already allocated.
                |
                */

                if (
                    $direction
                        === AdlRealignment::DIRECTION_TUPAD_TO_GIP
                    && (
                        $newAdjustedGrants < 0
                        || $newAdjustedGrants < $allocated
                    )
                ) {
                    return back()
                        ->withInput()
                        ->withErrors([
                            'amount' => sprintf(
                                'TUPAD to GIP realignment of ₱%s cannot be recorded. The adjusted TUPAD amount would become ₱%s, while existing allocations total ₱%s.',
                                number_format(
                                    $enteredAmount,
                                    2
                                ),
                                number_format(
                                    $newAdjustedGrants,
                                    2
                                ),
                                number_format(
                                    $allocated,
                                    2
                                )
                            ),
                        ]);
                }

                $lockedAdl
                    ->realignments()
                    ->create([
                        'direction' =>
                            $direction,

                        'amount' =>
                            $signedAmount,

                        'realignment_date' =>
                            $validated['realignment_date'],

                        'maf_date' =>
                            $validated['maf_date']
                                ?? $validated['realignment_date'],

                        'maf_number' =>
                            $validated['maf_number']
                                ?? (
                                    $validated['reference_number']
                                    ?? null
                                ),

                        'reference_number' =>
                            $validated['reference_number']
                                ?? (
                                    $validated['maf_number']
                                    ?? null
                                ),

                        'reason' =>
                            $validated['reason']
                                ?? null,

                        'created_by' =>
                            $request->user()->id,
                    ]);

                $directionLabel =
                    $direction
                        === AdlRealignment::DIRECTION_TUPAD_TO_GIP
                            ? 'TUPAD to GIP'
                            : 'GIP to TUPAD';

                return redirect()
                    ->route(
                        'adl.show',
                        $lockedAdl
                    )
                    ->with(
                        'success',
                        sprintf(
                            '%s realignment of ₱%s recorded successfully.',
                            $directionLabel,
                            number_format(
                                $enteredAmount,
                                2
                            )
                        )
                    );
            }
        );
    }
}
