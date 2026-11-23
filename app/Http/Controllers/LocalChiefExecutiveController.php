<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LocalChiefExecutiveController extends Controller
{
    /**
     * Focal-only: maintain the Governor of each province and the Mayor of each
     * city / municipality (Administration → Mayors & Governors). The names are
     * offered automatically as the LCE of an ADL allocation.
     */
    public function edit(Request $request): View
    {
        $validated = $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ]);

        $selectedProvinceId = isset($validated['province_id']) ? (int) $validated['province_id'] : null;

        $provinces = Province::query()
            ->with('governorUpdatedBy:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $municipalities = Municipality::query()
            ->with('mayorUpdatedBy:id,name')
            ->whereIn('province_id', $provinces->pluck('id'))
            ->when($selectedProvinceId, fn ($query) => $query->where('province_id', $selectedProvinceId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('province_id');

        $shownProvinces = $selectedProvinceId
            ? $provinces->where('id', $selectedProvinceId)->values()
            : $provinces;

        return view('locations.local-chief-executives.edit', [
            'provinces' => $provinces,
            'shownProvinces' => $shownProvinces,
            'selectedProvinceId' => $selectedProvinceId,
            'municipalitiesByProvince' => $municipalities,
            'missingCount' => $shownProvinces->whereNull('governor')->count()
                + $municipalities->flatten()->whereNull('mayor')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'governors' => ['nullable', 'array'],
            'governors.*' => ['nullable', 'string', 'max:255'],
            'mayors' => ['nullable', 'array'],
            'mayors.*' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;
        $changed = 0;

        DB::transaction(function () use ($validated, $userId, &$changed): void {
            $governors = $validated['governors'] ?? [];
            $mayors = $validated['mayors'] ?? [];

            $provinces = Province::query()
                ->whereIn('id', array_map('intval', array_keys($governors)))
                ->lockForUpdate()
                ->get();

            foreach ($provinces as $province) {
                $name = $this->cleanName($governors[$province->id] ?? null);

                if ($province->governor === $name) {
                    continue;
                }

                $province->forceFill([
                    'governor' => $name,
                    'governor_updated_by' => $userId,
                    'governor_updated_at' => now(),
                ])->save();

                $changed++;
            }

            $municipalities = Municipality::query()
                ->whereIn('id', array_map('intval', array_keys($mayors)))
                ->lockForUpdate()
                ->get();

            foreach ($municipalities as $municipality) {
                $name = $this->cleanName($mayors[$municipality->id] ?? null);

                if ($municipality->mayor === $name) {
                    continue;
                }

                $municipality->forceFill([
                    'mayor' => $name,
                    'mayor_updated_by' => $userId,
                    'mayor_updated_at' => now(),
                ])->save();

                $changed++;
            }
        });

        return redirect()
            ->route('locations.local-chief-executives.edit', array_filter([
                'province_id' => $validated['province_id'] ?? null,
            ]))
            ->with('success', $changed === 0
                ? 'No changes to save.'
                : sprintf('%d %s updated.', $changed, str('Mayor / Governor name')->plural($changed)));
    }

    /**
     * Stores the person's name only: a typed "Mayor " / "Governor " title is
     * dropped because the position is added when the name is used as an LCE.
     */
    private function cleanName(?string $value): ?string
    {
        $name = trim((string) preg_replace('/^\s*(hon\.?\s+)?(mayor|governor|gov\.?)\s+/i', '', (string) $value));

        return $name === '' ? null : $name;
    }
}
