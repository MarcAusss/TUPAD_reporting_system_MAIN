<?php

namespace Database\Seeders;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectImplementation;
use App\Models\ProjectInsuranceEnrollment;
use App\Models\ProjectLocation;
use App\Models\ProjectNoticeToProceed;
use App\Models\ProjectOrientation;
use App\Models\ProjectPpeDelivery;
use App\Models\Province;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CurrentSystemDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $tc = User::query()
                ->where('username', 'tc')
                ->firstOrFail();

            $focal = User::query()
                ->where('username', 'focal')
                ->firstOrFail();

            $adl = Adl::query()->updateOrCreate(
                [
                    'adl_number' => 'ADL-2026-CURRENT-DEMO',
                ],
                [
                    // ADL total follows the current rule: total = grants.
                    // Administrative cost is tracked separately.
                    'grants' => 60_000_000,
                    'admin_cost' => 1_800_000,
                    'total' => 60_000_000,
                    'created_by' => $focal->id,
                    'updated_by' => $focal->id,
                ]
            );

            $albayAllocation = $this->allocation(
                adl: $adl,
                focal: $focal,
                provinceName: 'Albay',
                partner: 'Provincial Government of Albay',
                amount: 30_000_000,
            );

            $camarinesSurAllocation = $this->allocation(
                adl: $adl,
                focal: $focal,
                provinceName: 'Camarines Sur',
                partner: 'Provincial Government of Camarines Sur',
                amount: 20_000_000,
            );

            $catanduanesAllocation = $this->allocation(
                adl: $adl,
                focal: $focal,
                provinceName: 'Catanduanes',
                partner: 'Provincial Government of Catanduanes',
                amount: 5_000_000,
            );

            $sorsogonAllocation = $this->allocation(
                adl: $adl,
                focal: $focal,
                provinceName: 'Sorsogon',
                partner: 'Provincial Government of Sorsogon',
                amount: 5_000_000,
            );

            /*
            |------------------------------------------------------------------
            | Project A — one project / one code / multiple districts
            |------------------------------------------------------------------
            |
            | This is the main Phase 11 sample. The project has ONE official
            | Project Code, but three municipalities from three Albay
            | congressional districts.
            |
            */

            $projectA = $this->project(
                tc: $tc,
                allocation: $albayAllocation,
                title: 'Albay Multi-District Community Works',
                provinceName: 'Albay',
                primaryMunicipalityName: 'Tabaco City',
                beneficiaries: 180,
                femaleBeneficiaries: 92,
                numberOfDays: 20,
                status: ProjectStatus::FOR_IMPLEMENTATION,
                partner: 'Provincial Government of Albay',
                remarks: 'Phase 11 demo: one project code covering multiple municipalities and districts.',
            );

            $this->syncLocations(
                project: $projectA,
                provinceName: 'Albay',
                municipalityNames: [
                    'Tabaco City',   // 1st District
                    'Daraga',        // 2nd District
                    'Guinobatan',    // 3rd District
                ],
                barangaysPerMunicipality: 2,
            );

            $this->approval(
                project: $projectA,
                tc: $tc,
                code: 'TUPAD-ALB-2026-001',
            );

            $futureStart = CarbonImmutable::today()->addDays(3);

            $this->prepareImplementation(
                project: $projectA,
                tc: $tc,
                startDate: $futureStart,
            );

            /*
            |------------------------------------------------------------------
            | Project B — second Albay project with a different code
            |------------------------------------------------------------------
            |
            | This intentionally proves that Projects -> Summary must display
            | only the selected project, even when another project exists in
            | the same province.
            |
            */

            $projectB = $this->project(
                tc: $tc,
                allocation: $albayAllocation,
                title: 'Albay Urban Drainage Clearing',
                provinceName: 'Albay',
                primaryMunicipalityName: 'Legazpi City',
                beneficiaries: 70,
                femaleBeneficiaries: 36,
                numberOfDays: 15,
                status: ProjectStatus::APPROVED,
                partner: 'City Government of Legazpi',
                remarks: 'Second Albay demo project. It must not appear inside Project A summary.',
            );

            $this->syncLocations(
                project: $projectB,
                provinceName: 'Albay',
                municipalityNames: [
                    'Legazpi City',
                ],
                barangaysPerMunicipality: 2,
            );

            $this->approval(
                project: $projectB,
                tc: $tc,
                code: 'TUPAD-ALB-2026-002',
            );

            /*
            |------------------------------------------------------------------
            | Project C — For Approval, deliberately has NO project code yet
            |------------------------------------------------------------------
            |
            | Open this project and approve it manually. The approval form is
            | where the official Project Code must be assigned.
            |
            */

            $projectC = $this->project(
                tc: $tc,
                allocation: $camarinesSurAllocation,
                title: 'Camarines Sur Community Rehabilitation',
                provinceName: 'Camarines Sur',
                primaryMunicipalityName: 'Naga City',
                beneficiaries: 100,
                femaleBeneficiaries: 54,
                numberOfDays: 20,
                status: ProjectStatus::FOR_APPROVAL,
                partner: 'City Government of Naga',
                remarks: 'Approval demo: assign the official Project Code from the Project Approval form.',
            );

            $this->syncLocations(
                project: $projectC,
                provinceName: 'Camarines Sur',
                municipalityNames: [
                    'Naga City',
                    'Pili',
                ],
                barangaysPerMunicipality: 2,
            );

            // Ensure rerunning this seeder never leaves a stale approval on the
            // For Approval demonstration project.
            ProjectApproval::query()
                ->where('project_id', $projectC->id)
                ->delete();

            /*
            |------------------------------------------------------------------
            | Project D — Ongoing Implementation
            |------------------------------------------------------------------
            */

            $projectD = $this->project(
                tc: $tc,
                allocation: $catanduanesAllocation,
                title: 'Catanduanes Coastal Rehabilitation',
                provinceName: 'Catanduanes',
                primaryMunicipalityName: 'Virac',
                beneficiaries: 80,
                femaleBeneficiaries: 41,
                numberOfDays: 20,
                status: ProjectStatus::ONGOING_IMPLEMENTATION,
                partner: 'LGU Virac',
                remarks: 'Implementation board demo: currently ongoing.',
            );

            $this->syncLocations(
                project: $projectD,
                provinceName: 'Catanduanes',
                municipalityNames: [
                    'Virac',
                    'San Andres',
                ],
                barangaysPerMunicipality: 2,
            );

            $this->approval(
                project: $projectD,
                tc: $tc,
                code: 'TUPAD-CAT-2026-001',
            );

            $this->prepareImplementation(
                project: $projectD,
                tc: $tc,
                startDate: CarbonImmutable::today()->subDays(5),
            );

            /*
            |------------------------------------------------------------------
            | Project E — For Submission of Post Docs
            |------------------------------------------------------------------
            */

            $projectE = $this->project(
                tc: $tc,
                allocation: $sorsogonAllocation,
                title: 'Sorsogon Public Area Rehabilitation',
                provinceName: 'Sorsogon',
                primaryMunicipalityName: 'Sorsogon City',
                beneficiaries: 60,
                femaleBeneficiaries: 31,
                numberOfDays: 20,
                status: ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                partner: 'City Government of Sorsogon',
                remarks: 'Implementation board demo: work period already ended.',
            );

            $this->syncLocations(
                project: $projectE,
                provinceName: 'Sorsogon',
                municipalityNames: [
                    'Sorsogon City',
                ],
                barangaysPerMunicipality: 2,
            );

            $this->approval(
                project: $projectE,
                tc: $tc,
                code: 'TUPAD-SOR-2026-001',
            );

            $this->prepareImplementation(
                project: $projectE,
                tc: $tc,
                startDate: CarbonImmutable::today()->subDays(25),
            );
        });

        $this->command?->info(
            'Current TUPAD demo data seeded: one-code-per-project, multi-location projects, approval demo, and implementation workflow samples.'
        );
    }

    private function allocation(
        Adl $adl,
        User $focal,
        string $provinceName,
        string $partner,
        float $amount,
    ): AdlAllocation {
        return AdlAllocation::query()->updateOrCreate(
            [
                'adl_id' => $adl->id,
                'partner' => $partner,
            ],
            [
                'fund_sponsor' => 'DOLE Regional Office V',
                'location' => $provinceName,
                'amount' => $amount,
                'remarks' => "Current system demo allocation for {$provinceName}.",
                'created_by' => $focal->id,
                'updated_by' => $focal->id,
            ]
        );
    }

    private function project(
        User $tc,
        AdlAllocation $allocation,
        string $title,
        string $provinceName,
        string $primaryMunicipalityName,
        int $beneficiaries,
        int $femaleBeneficiaries,
        int $numberOfDays,
        ProjectStatus $status,
        string $partner,
        string $remarks,
    ): Project {
        $province = Province::query()
            ->where('name', $provinceName)
            ->firstOrFail();

        $municipality = $this->municipality(
            province: $province,
            requestedName: $primaryMunicipalityName,
        );

        $barangay = Barangay::query()
            ->where('municipality_id', $municipality->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->firstOrFail();

        $wageRate = 455.00;
        $ppePerBeneficiary = 350.00;
        $insuranceRate = 50.00;

        $wagesTotal = $wageRate * $beneficiaries * $numberOfDays;
        $ppeTotal = $ppePerBeneficiary * $beneficiaries;
        $insuranceTotal = $insuranceRate * $beneficiaries;

        $project = Project::query()->updateOrCreate(
            [
                'adl_allocation_id' => $allocation->id,
                'project_title' => $title,
            ],
            [
                'date_received' => CarbonImmutable::today()->subDays(30)->toDateString(),
                'nature_of_work' => 'Community rehabilitation, clean-up, maintenance, and public-area improvement.',

                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => $partner,

                'project_series' => 'Regular TUPAD 2026',
                'project_series_remarks' => 'Current-system demonstration data.',
                'tevs_date_verified' => CarbonImmutable::today()->subDays(25)->toDateString(),
                'tevs_remarks' => 'Verified for current-system demonstration.',

                'province_id' => $province->id,
                'municipality_id' => $municipality->id,
                'barangay_id' => $barangay->id,

                'province' => $province->name,
                'district' => $municipality->district,
                'municipality' => $municipality->name,
                'barangay' => $barangay->name,
                'income_class' => $municipality->income_class,

                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'number_of_days' => $numberOfDays,
                'term' => ProjectTerm::fromDays($numberOfDays),

                'beneficiaries_total' => $beneficiaries,
                'beneficiaries_female' => $femaleBeneficiaries,

                'wage_rate' => $wageRate,
                'wages_total' => $wagesTotal,

                'ppe_total' => $ppeTotal,

                'insurance_rate' => $insuranceRate,
                'insurance_beneficiaries' => $beneficiaries,
                'insurance_total' => $insuranceTotal,

                'total_project_cost' => $wagesTotal + $ppeTotal + $insuranceTotal,

                'status' => $status,
                'remarks' => $remarks,
                'created_by' => $tc->id,
                'updated_by' => $tc->id,
            ]
        );

        $project->ppeItems()->updateOrCreate(
            [
                'product' => 'Standard TUPAD PPE Set',
            ],
            [
                'ppe_type' => PpeType::NON_HAZARDOUS,
                'beneficiary_count' => $beneficiaries,
                'unit_amount' => $ppePerBeneficiary,
                'total_amount' => $ppeTotal,
            ]
        );

        return $project;
    }

    private function syncLocations(
        Project $project,
        string $provinceName,
        array $municipalityNames,
        int $barangaysPerMunicipality = 2,
    ): void {
        $province = Province::query()
            ->where('name', $provinceName)
            ->firstOrFail();

        $keepLocationIds = [];

        foreach (array_values($municipalityNames) as $index => $municipalityName) {
            $municipality = $this->municipality(
                province: $province,
                requestedName: $municipalityName,
            );

            $barangayIds = Barangay::query()
                ->where('municipality_id', $municipality->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit($barangaysPerMunicipality)
                ->pluck('id');

            if ($barangayIds->isEmpty()) {
                throw new \RuntimeException(
                    "No active barangay exists for {$municipality->name}, {$province->name}."
                );
            }

            $location = ProjectLocation::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'municipality_id' => $municipality->id,
                ],
                [
                    'province_id' => $province->id,
                    'district' => $municipality->district,
                    'sort_order' => $index + 1,
                ]
            );

            $location->barangays()->sync($barangayIds->all());
            $keepLocationIds[] = $location->id;
        }

        ProjectLocation::query()
            ->where('project_id', $project->id)
            ->whereNotIn('id', $keepLocationIds)
            ->delete();
    }

    /**
     * Resolve a municipality/city using the PSGC name stored by
     * BicolLocationSeeder.
     *
     * PSGC may store city names as "City of Tabaco", "City of Legazpi",
     * "City of Naga", etc., while the UI/business wording commonly uses
     * "Tabaco City", "Legazpi City", "Naga City".
     *
     * Both forms identify the same location and must not make demo seeding
     * fail.
     */
    private function municipality(
        Province $province,
        string $requestedName,
    ): Municipality {
        $requestedKey = $this->normalizeMunicipalityName(
            $requestedName
        );

        $municipality = Municipality::query()
            ->where('province_id', $province->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->first(
                fn (Municipality $candidate): bool =>
                    $this->normalizeMunicipalityName(
                        $candidate->name
                    ) === $requestedKey
            );

        if ($municipality) {
            return $municipality;
        }

        $available = Municipality::query()
            ->where('province_id', $province->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->implode(', ');

        throw new RuntimeException(
            "Unable to resolve municipality/city [{$requestedName}] "
            . "inside {$province->name}. Active PSGC names: {$available}"
        );
    }

    private function normalizeMunicipalityName(
        string $name
    ): string {
        return Str::of($name)
            ->lower()
            ->replace('city of ', '')
            ->replace(' city', '')
            ->replace('ñ', 'n')
            ->replace('á', 'a')
            ->replace('é', 'e')
            ->replace('í', 'i')
            ->replace('ó', 'o')
            ->replace('ú', 'u')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function approval(
        Project $project,
        User $tc,
        string $code,
    ): void {
        ProjectApproval::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'approval_date' => CarbonImmutable::today()->subDays(10)->toDateString(),
                'project_code' => $code,
                'remarks' => 'Current-system demo approval. This is the single official Project Code for this project.',
                'approved_by' => $tc->id,
                'approved_at' => CarbonImmutable::today()->subDays(10)->setTime(9, 0),
            ]
        );
    }

    private function prepareImplementation(
        Project $project,
        User $tc,
        CarbonImmutable $startDate,
    ): void {
        $endDate = $startDate->addDays((int) $project->number_of_days);

        ProjectInsuranceEnrollment::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'date_enrolled' => $startDate->subDays(8)->toDateString(),
                'beneficiary_count' => (int) $project->insurance_beneficiaries,
                'amount' => (float) $project->insurance_total,
                'payment_mode' => 'cash',
                'or_number' => 'OR-' . str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'policy_number' => 'POL-' . str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'remarks' => 'Current-system demo insurance enrollment.',
                'recorded_by' => $tc->id,
            ]
        );

        ProjectPpeDelivery::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'delivery_receipt_date' => $startDate->subDays(7)->toDateString(),
                'ppe_provided' => 'Standard TUPAD PPE Set',
                'inventory_reference' => 'PPE-' . str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'remarks' => 'Current-system demo PPE delivery.',
                'recorded_by' => $tc->id,
            ]
        );

        ProjectNoticeToProceed::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'date_issued' => $startDate->subDays(6)->toDateString(),
                'date_released' => $startDate->subDays(5)->toDateString(),
                'remarks' => 'Current-system demo Notice to Proceed.',
                'recorded_by' => $tc->id,
            ]
        );

        ProjectOrientation::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'orientation_date' => $startDate->subDays(4)->toDateString(),
                'remarks' => 'Current-system demo beneficiary orientation.',
                'recorded_by' => $tc->id,
            ]
        );

        ProjectImplementation::query()->updateOrCreate(
            [
                'project_id' => $project->id,
            ],
            [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'remarks' => 'End Date is automatically based on Start Date + project number of days.',
                'recorded_by' => $tc->id,
            ]
        );
    }
}
