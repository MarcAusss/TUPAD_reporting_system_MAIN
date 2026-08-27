<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMonitoringDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProvinceMonitoringController extends Controller
{
    public function index(Request $request, string $province): View
    {
        $provinceName = $this->resolveProvince($province);
        $projects = Project::query()
            ->where('province', $provinceName)
            ->with([
                'allocation.adl', 'approval', 'monitoringDetail', 'beneficiaries',
                'insuranceEnrollment', 'ppeDelivery', 'noticeToProceed', 'implementation',
                'obligations', 'postDocuments',
            ])
            ->latest('date_received')
            ->paginate(25)
            ->withQueryString();

        return view('monitoring.province-projects', compact('projects', 'provinceName'));
    }

    public function edit(Project $project): View
    {
        $project->load(['allocation.adl', 'approval', 'monitoringDetail', 'beneficiaries']);
        return view('projects.monitoring.edit', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'project_series' => ['nullable', 'string', 'max:255'],
            'proponent' => ['nullable', 'string', 'max:255'],
            'receipt_month' => ['nullable', 'string', 'max:30'],
            'receipt_datetime' => ['nullable', 'date'],
            'process_cycle_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'compliance_date' => ['nullable', 'date'],
            'compliance_reference' => ['nullable', 'string', 'max:150'],
            'agreement_type' => ['nullable', Rule::in(['COS', 'MOA', 'MOU'])],
            'agreement_date' => ['nullable', 'date'],
            'agreement_reference' => ['nullable', 'string', 'max:150'],
            'replacement_request_date' => ['nullable', 'date'],
            'replacement_ntp_date' => ['nullable', 'date'],
            'voucher_date' => ['nullable', 'date'],
            'voucher_number' => ['nullable', 'string', 'max:150'],
            'nafa_date' => ['nullable', 'date'],
            'nafa_number' => ['nullable', 'string', 'max:150'],
            'sprs_date' => ['nullable', 'date'],
            'cqpr_date' => ['nullable', 'date'],
            'transparency_seal_date' => ['nullable', 'date'],
            'monitoring_remarks' => ['nullable', 'string', 'max:4000'],
        ]);

        ProjectMonitoringDetail::updateOrCreate(
            ['project_id' => $project->id],
            [...$validated, 'updated_by' => $request->user()->id]
        );

        return redirect()->route('project-monitoring.province', ['province' => (string) str($project->province)->slug()])
            ->with('success', 'Provincial monitoring details updated successfully.');
    }

    private function resolveProvince(string $province): string
    {
        $matched = collect(config('tupad.provinces', []))->first(
            fn (string $name) => (string) str($name)->slug() === (string) str($province)->slug()
        );
        abort_unless($matched, 404);
        return $matched;
    }
}
