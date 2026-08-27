<?php

namespace App\Http\Controllers;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Adl;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Exports\PdfTableWriter;
use App\Services\Exports\XlsxTableWriter;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerationService $reports,
        private readonly PdfTableWriter $pdf,
        private readonly XlsxTableWriter $xlsx,
    ) {}

    public function index(Request $request): View
    {
        [$type, $dimension, $filters] = $this->reportRequest($request);

        return view('reports.index', [
            'report' => $this->reports->generate($type, $dimension, $filters),
            'options' => $this->options(),
            'query' => $this->cleanQuery($request),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $report = $this->generate($request);
        $filename = $report['file_base_name'].'.csv';

        return response()->streamDownload(
            function () use ($report): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    return;
                }

                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [$report['title']]);
                fputcsv($handle, [
                    'Generated',
                    $report['generated_at']->format('Y-m-d H:i:s'),
                ]);

                foreach ($report['criteria'] as $label => $value) {
                    fputcsv($handle, [$label, $this->csvValue((string) $value)]);
                }

                if ($report['warning']) {
                    fputcsv($handle, ['Note', $this->csvValue($report['warning'])]);
                }

                fputcsv($handle, []);
                fputcsv(
                    $handle,
                    collect($report['columns'])->pluck('label')->all(),
                );

                foreach ($report['display_rows'] as $row) {
                    fputcsv(
                        $handle,
                        collect($report['columns'])
                            ->map(fn (array $column): string =>
                                $this->csvValue((string) ($row[$column['key']] ?? '—')))
                            ->all(),
                    );
                }

                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function exportPdf(Request $request): Response
    {
        $report = $this->generate($request);

        return response($this->pdf->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s.pdf"',
                $report['file_base_name'],
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $report = $this->generate($request);
        $path = $this->xlsx->write($report);

        return response()
            ->download(
                $path,
                $report['file_base_name'].'.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            )
            ->deleteFileAfterSend(true);
    }

    public function print(Request $request): View
    {
        return view('reports.print', [
            'report' => $this->generate($request),
        ]);
    }

    private function generate(Request $request): array
    {
        [$type, $dimension, $filters] = $this->reportRequest($request);

        return $this->reports->generate($type, $dimension, $filters);
    }

    /** @return array{0: ReportType, 1: ReportDimension, 2: ReportFilters} */
    private function reportRequest(Request $request): array
    {
        $rawType = $request->query('report_type');
        $requestedType = is_string($rawType)
            ? ReportType::tryFrom($rawType)
            : null;
        $defaultType = $requestedType ?? ReportType::PHYSICAL_FINANCIAL;
        $input = array_merge(
            [
                'report_type' => $defaultType->value,
                'group_by' => $defaultType->defaultDimension()->value,
            ],
            $request->query(),
        );

        $validator = Validator::make($input, [
            'report_type' => ['required', Rule::enum(ReportType::class)],
            'group_by' => ['required', Rule::enum(ReportDimension::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'fiscal_year' => [
                'nullable',
                'integer',
                'between:2000,2100',
                'required_with:quarter,month',
            ],
            'quarter' => [
                'nullable',
                'integer',
                'between:1,4',
            ],
            'month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
            'term' => ['nullable', Rule::enum(ProjectTerm::class)],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'adl_id' => ['nullable', 'integer', 'exists:adls,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district' => ['nullable', 'string', 'max:100'],
            'municipality_id' => [
                'nullable',
                'integer',
                'exists:municipalities,id',
            ],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'sponsor' => ['nullable', 'string', 'max:255'],
            'partner' => ['nullable', 'string', 'max:255'],
            'project_code' => ['nullable', 'string', 'max:255'],
            'sector' => [
                'nullable',
                Rule::enum(BeneficiarySectorCategory::class),
            ],
            'intervention_focus' => [
                'nullable',
                Rule::enum(ProjectInterventionFocus::class),
            ],
            'labor_market_program' => [
                'nullable',
                Rule::enum(LaborMarketProgram::class),
            ],
        ]);

        $validator->after(function ($validator) use ($input): void {
            $hasQuarter = array_key_exists('quarter', $input)
                && $input['quarter'] !== null
                && $input['quarter'] !== '';
            $hasMonth = array_key_exists('month', $input)
                && $input['month'] !== null
                && $input['month'] !== '';

            if ($hasQuarter && $hasMonth) {
                $validator->errors()->add(
                    'quarter',
                    'Quarter and month cannot be used at the same time.'
                );
                $validator->errors()->add(
                    'month',
                    'Month and quarter cannot be used at the same time.'
                );
            }
        });

        $validated = $validator->validate();

        $type = ReportType::from($validated['report_type']);
        $dimension = ReportDimension::from($validated['group_by']);

        if (! $type->allows($dimension)) {
            throw ValidationException::withMessages([
                'group_by' => sprintf(
                    '%s cannot be grouped by %s.',
                    $type->label(),
                    $dimension->label(),
                ),
            ]);
        }

        return [$type, $dimension, ReportFilters::fromArray($validated)];
    }

    private function options(): array
    {
        return [
            'report_types' => ReportType::cases(),
            'dimensions' => ReportDimension::cases(),
            'terms' => ProjectTerm::cases(),
            'statuses' => ProjectStatus::cases(),
            'sectors' => BeneficiarySectorCategory::cases(),
            'intervention_focuses' => ProjectInterventionFocus::cases(),
            'labor_market_programs' => LaborMarketProgram::cases(),
            'adls' => Adl::query()
                ->orderByDesc('date_received')
                ->orderBy('adl_number')
                ->get(['id', 'adl_number']),
            'provinces' => Province::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'municipalities' => Municipality::query()
                ->with('province:id,name')
                ->orderBy('name')
                ->get(['id', 'province_id', 'name']),
            'barangays' => Barangay::query()
                ->with('municipality:id,province_id,name')
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name']),
            'districts' => Project::query()
                ->whereNotNull('district')
                ->where('district', '!=', '')
                ->distinct()
                ->orderBy('district')
                ->pluck('district'),
            'sponsors' => Project::query()
                ->whereNotNull('fund_sponsor')
                ->where('fund_sponsor', '!=', '')
                ->distinct()
                ->orderBy('fund_sponsor')
                ->pluck('fund_sponsor'),
            'partners' => Project::query()
                ->whereNotNull('partner')
                ->where('partner', '!=', '')
                ->distinct()
                ->orderBy('partner')
                ->pluck('partner'),
        ];
    }

    private function cleanQuery(Request $request): array
    {
        return collect($request->query())
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function csvValue(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value))
            ? "'".$value
            : $value;
    }
}
