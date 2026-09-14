<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Projects\ProjectBeneficiaryAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectBeneficiaryAddressController extends Controller
{
    public function __construct(
        private readonly ProjectBeneficiaryAddressService $addressService,
    ) {}

    public function update(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        $provinceId = $this->addressService->beneficiaryProvinceId($project, $user);

        abort_unless(
            $provinceId !== null,
            422,
            'The project does not have a valid province for beneficiary address encoding.'
        );

        $validated = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'beneficiary_addresses' => ['required', 'array', 'min:1'],
        ]);

        $this->addressService->sync(
            project: $project,
            user: $user,
            provinceId: $provinceId,
            submittedProvinceId: (int) $validated['province_id'],
            addresses: $validated['beneficiary_addresses'],
        );

        return redirect(
            route('projects.show', [
                'project' => $project,
                'workspace' => 'beneficiaries',
            ]).'#beneficiary-classification'
        )->with('success', 'Beneficiary address allocation saved successfully.');
    }
}
