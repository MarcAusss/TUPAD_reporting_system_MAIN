<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Support\MunicipalityIncomeClasses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MunicipalityIncomeClassController extends Controller
{
    /**
     * Focal-only: review and adjust the income class of every municipality/city.
     */
    public function edit(Request $request): View
    {
        $validated = $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);

        $provinces = Province::query()
            ->where('is_active', true)
            ->whereIn('name', array_keys(MunicipalityIncomeClasses::INITIAL))
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedProvinceId = isset($validated['province_id']) ? (int) $validated['province_id'] : null;

        $municipalities = Municipality::query()
            ->with(['province:id,name', 'incomeClassUpdatedBy:id,name'])
            ->whereIn('province_id', $provinces->pluck('id'))
            ->when($selectedProvinceId, fn ($query) => $query->where('province_id', $selectedProvinceId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('province_id');

        return view('locations.income-classes.edit', [
            'provinces' => $provinces,
            'selectedProvinceId' => $selectedProvinceId,
            'municipalitiesByProvince' => $municipalities,
            'options' => MunicipalityIncomeClasses::OPTIONS,
            'unsetCount' => $municipalities->flatten()->whereNull('income_class')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'income_classes' => ['required', 'array', 'min:1'],
            'income_classes.*' => ['nullable', 'string', Rule::in(MunicipalityIncomeClasses::OPTIONS)],
        ]);

        $userId = $request->user()->id;
        $changed = 0;

        DB::transaction(function () use ($validated, $userId, &$changed): void {
            $municipalities = Municipality::query()
                ->whereIn('id', array_map('intval', array_keys($validated['income_classes'])))
                ->lockForUpdate()
                ->get();

            foreach ($municipalities as $municipality) {
                $class = $validated['income_classes'][$municipality->id] ?? null;
                $class = filled($class) ? $class : null;

                if ($municipality->income_class === $class) {
                    continue;
                }

                $municipality->forceFill([
                    'income_class' => $class,
                    'income_class_updated_by' => $userId,
                    'income_class_updated_at' => now(),
                ])->save();

                // Keep the project compatibility snapshot in step with the municipality.
                Project::query()
                    ->where('municipality_id', $municipality->id)
                    ->update(['income_class' => $class]);

                $changed++;
            }
        });

        return redirect()
            ->route('locations.income-classes.edit', array_filter([
                'province_id' => $validated['province_id'] ?? null,
            ]))
            ->with('success', $changed === 0
                ? 'No income class changes to save.'
                : "Income class updated for {$changed} ".str('municipality')->plural($changed).'.');
    }
}
