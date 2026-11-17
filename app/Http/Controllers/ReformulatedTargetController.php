<?php

namespace App\Http\Controllers;

use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Province;
use App\Models\ReformulatedTarget;
use App\Reports\ReportFilters;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReformulatedTargetController extends Controller
{
    private const BICOL_PROVINCES = [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ];

    public function __construct(
        private readonly ReportGenerationService $reports,
    ) {}

    public function edit(Request $request): View
    {
        $validated = $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $fiscalYear = (int) ($validated['fiscal_year'] ?? now('Asia/Manila')->year);

        $provinces = Province::query()
            ->whereIn('name', self::BICOL_PROVINCES)
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->sortBy(fn (Province $province): int => array_search(
                $province->name,
                self::BICOL_PROVINCES,
                true,
            ))
            ->values();

        $savedTargets = ReformulatedTarget::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereIn('province_id', $provinces->pluck('id'))
            ->get()
            ->keyBy('province_id');

        $report = $this->reports->generate(
            ReportType::PHYSICAL_FINANCIAL,
            ReportDimension::OVERALL,
            new ReportFilters(fiscalYear: $fiscalYear),
        );

        $matrixRows = collect(
            data_get($report, 'physical_financial_matrix.rows', [])
        )->keyBy('province');

        $rows = $provinces->map(function (Province $province) use (
            $savedTargets,
            $matrixRows,
        ): array {
            /** @var ReformulatedTarget|null $saved */
            $saved = $savedTargets->get($province->id);
            $matrixRow = $matrixRows->get($province->name, []);

            // Saved split wins; otherwise prefill each term from that term's projects.
            $term = function (ProjectTerm $term) use ($saved, $matrixRow): array {
                $values = $saved?->termTarget($term)
                    ?? data_get($matrixRow, 'terms.'.$term->value.'.derived_target', []);

                return [
                    'physical' => (int) ($values['physical'] ?? 0),
                    'financial' => $this->centsToPeso((int) ($values['financial_cents'] ?? 0)),
                ];
            };

            return [
                'province_id' => $province->id,
                'province' => $province->name,
                'short_term' => $term(ProjectTerm::SHORT_TERM),
                'long_term' => $term(ProjectTerm::LONG_TERM),
                'saved' => $saved !== null,
                'split' => (bool) $saved?->hasTermSplit(),
                // Combined total saved before the term split existed, shown for reference.
                'legacy_total' => $saved !== null && ! $saved->hasTermSplit()
                    ? [
                        'physical' => $saved->physical_target,
                        'financial' => $this->centsToPeso($saved->financial_target_cents),
                    ]
                    : null,
                'updated_at' => $saved?->updated_at,
            ];
        });

        return view('reports.reformulated-targets.edit', [
            'fiscalYear' => $fiscalYear,
            'rows' => $rows,
            'missingProvinceCount' => count(self::BICOL_PROVINCES) - $provinces->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $allowedProvinceIds = Province::query()
            ->whereIn('name', self::BICOL_PROVINCES)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.province_id' => [
                'required',
                'integer',
                'distinct',
                Rule::in($allowedProvinceIds),
            ],
            'targets.*.short_term_physical_target' => ['required', 'integer', 'min:0', 'max:100000000'],
            'targets.*.long_term_physical_target' => ['required', 'integer', 'min:0', 'max:100000000'],
            'targets.*.short_term_financial_target' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'targets.*.long_term_financial_target' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
        ], [
            'targets.*.short_term_financial_target.regex' =>
                'Short-Term financial target must be a valid peso amount with up to two decimal places and no commas.',
            'targets.*.long_term_financial_target.regex' =>
                'Long-Term financial target must be a valid peso amount with up to two decimal places and no commas.',
        ]);

        $fiscalYear = (int) $validated['fiscal_year'];
        $userId = $request->user()->id;

        DB::transaction(function () use ($validated, $fiscalYear, $userId): void {
            foreach ($validated['targets'] as $target) {
                $shortPhysical = (int) $target['short_term_physical_target'];
                $longPhysical = (int) $target['long_term_physical_target'];
                $shortCents = $this->pesoToCents((string) $target['short_term_financial_target']);
                $longCents = $this->pesoToCents((string) $target['long_term_financial_target']);

                ReformulatedTarget::query()->updateOrCreate(
                    [
                        'province_id' => (int) $target['province_id'],
                        'fiscal_year' => $fiscalYear,
                    ],
                    [
                        // The combined target always equals short + long.
                        'physical_target' => $shortPhysical + $longPhysical,
                        'financial_target_cents' => $shortCents + $longCents,
                        'short_term_physical_target' => $shortPhysical,
                        'short_term_financial_target_cents' => $shortCents,
                        'long_term_physical_target' => $longPhysical,
                        'long_term_financial_target_cents' => $longCents,
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        return redirect()
            ->route('reports.reformulated-targets.edit', [
                'fiscal_year' => $fiscalYear,
            ])
            ->with(
                'success',
                'Reformulated targets for FY'.$fiscalYear.' were saved successfully.'
            );
    }

    private function pesoToCents(string $value): int
    {
        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function centsToPeso(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
