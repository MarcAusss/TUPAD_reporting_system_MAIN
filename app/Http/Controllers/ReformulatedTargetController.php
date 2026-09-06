<?php

namespace App\Http\Controllers;

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

            $physical = $saved?->physical_target
                ?? (int) data_get($matrixRow, 'target.physical', 0);

            $financialCents = $saved?->financial_target_cents
                ?? (int) data_get($matrixRow, 'target.financial_cents', 0);

            return [
                'province_id' => $province->id,
                'province' => $province->name,
                'physical_target' => $physical,
                'financial_target' => number_format(
                    $financialCents / 100,
                    2,
                    '.',
                    '',
                ),
                'saved' => $saved !== null,
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
            'targets.*.physical_target' => [
                'required',
                'integer',
                'min:0',
                'max:100000000',
            ],
            'targets.*.financial_target' => [
                'required',
                'string',
                'regex:/^\d{1,12}(?:\.\d{1,2})?$/',
            ],
        ], [
            'targets.*.financial_target.regex' =>
                'Financial target must be a valid peso amount with up to two decimal places and no commas.',
        ]);

        $fiscalYear = (int) $validated['fiscal_year'];
        $userId = $request->user()->id;

        DB::transaction(function () use ($validated, $fiscalYear, $userId): void {
            foreach ($validated['targets'] as $target) {
                ReformulatedTarget::query()->updateOrCreate(
                    [
                        'province_id' => (int) $target['province_id'],
                        'fiscal_year' => $fiscalYear,
                    ],
                    [
                        'physical_target' => (int) $target['physical_target'],
                        'financial_target_cents' => $this->pesoToCents(
                            (string) $target['financial_target']
                        ),
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
}
