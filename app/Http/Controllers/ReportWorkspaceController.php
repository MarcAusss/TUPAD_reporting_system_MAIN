<?php

namespace App\Http\Controllers;

use App\Reports\ReportWorkspaceCatalog;
use Illuminate\View\View;

class ReportWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReportWorkspaceCatalog $catalog,
    ) {}

    public function physicalFinancial(): View
    {
        return $this->render('physical-financial');
    }

    public function fundStatus(): View
    {
        return $this->render('fund-status');
    }

    public function monthly(): View
    {
        return $this->render('monthly');
    }

    public function quarterly(): View
    {
        return $this->render('quarterly');
    }

    public function geographicMapping(): View
    {
        return $this->render('geographic-mapping');
    }

    private function render(string $key): View
    {
        $section = $this->catalog->section($key);
        abort_if($section === null, 404);

        return view('reports.workspace', [
            'activeKey' => $key,
            'section' => $section,
            'sections' => $this->catalog->sections(),
        ]);
    }
}
