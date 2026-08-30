<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectStatus;
use App\Services\Exports\PdfTableWriter;
use App\Services\Reports\OfficialPeriodicReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficialPeriodicReportController extends Controller
{
    public function __construct(
        private readonly OfficialPeriodicReportService $reports,
        private readonly PdfTableWriter $pdf,
    ) {}

    public function print(Request $request): View
    {
        return view('reports.print', ['report' => $this->build($request)]);
    }

    public function exportPdf(Request $request): Response
    {
        $report = $this->build($request);

        return response($this->pdf->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $report['file_base_name']),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function build(Request $request): array
    {
        $form = (string) $request->query('form');
        $monthly = in_array($form, ['sprs', 'orientations'], true);
        $quarterly = in_array($form, ['cqpr', 'labor-market'], true);
        abort_unless($monthly || $quarterly, 404);

        $rules = [
            'form' => ['required', Rule::in(['sprs', 'orientations', 'cqpr', 'labor-market'])],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ];
        if ($monthly) $rules['month'] = ['required', 'integer', 'between:1,12'];
        if ($quarterly) $rules['quarter'] = ['required', 'integer', 'between:1,4'];
        if ($form === 'labor-market') $rules['labor_market_program'] = ['nullable', Rule::enum(LaborMarketProgram::class)];

        $validated = validator($request->query(), $rules)
            ->after(function ($validator) use ($request): void {
                $mode = ImplementationMode::tryFrom((string) $request->query('implementation_mode'));
                $status = ProjectStatus::tryFrom((string) $request->query('status'));
                if ($mode === ImplementationMode::DIRECT_ADMINISTRATION && in_array($status, [ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT, ProjectStatus::FOR_LIQUIDATION, ProjectStatus::PARTIALLY_LIQUIDATED], true)) {
                    $validator->errors()->add('status', 'The selected status belongs only to Through ACP projects.');
                }
                if ($mode === ImplementationMode::THROUGH_ACP && $status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS) {
                    $validator->errors()->add('status', 'For Submission of Post-Docs belongs only to Direct Administration projects.');
                }
            })
            ->validate();

        return $this->reports->build($form, $validated, $request->user());
    }
}
