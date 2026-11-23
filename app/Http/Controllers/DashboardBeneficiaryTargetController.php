<?php

namespace App\Http\Controllers;

use App\Enums\ProjectTerm;
use App\Models\BeneficiaryTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Saves the Focal dashboard's short-term / long-term beneficiary targets. */
class DashboardBeneficiaryTargetController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('beneficiaryTargets', [
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'short_term' => ['required', 'integer', 'min:0', 'max:10000000'],
            'long_term' => ['required', 'integer', 'min:0', 'max:10000000'],
        ], [], [
            'short_term' => 'short-term target',
            'long_term' => 'long-term target',
        ]);

        $year = (int) $validated['fiscal_year'];

        DB::transaction(function () use ($validated, $year, $request): void {
            foreach (ProjectTerm::cases() as $term) {
                BeneficiaryTarget::query()->updateOrCreate(
                    ['fiscal_year' => $year, 'term' => $term->value],
                    ['target' => (int) $validated[$term->value], 'updated_by' => $request->user()->id],
                );
            }
        });

        return redirect()
            ->back(fallback: route('dashboard'))
            ->with('success', 'Beneficiary targets for FY'.$year.' were saved.');
    }
}
