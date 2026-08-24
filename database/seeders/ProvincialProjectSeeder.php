<?php

namespace Database\Seeders;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProvincialProjectSeeder extends Seeder
{
    /**
     * Bicol Region provinces used by Provincial Monitoring.
     */
    private const PROVINCES = [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $tc = $this->resolveTcUser();

            $adl = $this->createOrUpdateDemoAdl($tc);

            foreach (self::PROVINCES as $provinceName) {
                $province = Province::query()
                    ->where('name', $provinceName)
                    ->first();

                if (! $province) {
                    $this->command?->warn(
                        "Province '{$provinceName}' was not found. Skipping."
                    );

                    continue;
                }

                $municipalities = Municipality::query()
                    ->where('province_id', $province->id)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();

                if ($municipalities->isEmpty()) {
                    $this->command?->warn(
                        "No active municipalities found for {$provinceName}. Skipping."
                    );

                    continue;
                }

                $allocation = $this->createOrUpdateAllocation(
                    $adl,
                    $province,
                    $tc
                );

                for ($index = 1; $index <= 3; $index++) {
                    $municipality = $municipalities[
                        ($index - 1) % $municipalities->count()
                    ];

                    $barangay = Barangay::query()
                        ->where(
                            'municipality_id',
                            $municipality->id
                        )
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->first();

                    if (! $barangay) {
                        $this->command?->warn(
                            "No barangay found for {$municipality->name}, {$provinceName}. Project {$index} skipped."
                        );

                        continue;
                    }

                    $this->createProject(
                        province: $province,
                        municipality: $municipality,
                        barangay: $barangay,
                        allocation: $allocation,
                        tc: $tc,
                        sequence: $index,
                    );
                }
            }
        });

        $count = Project::query()
            ->where('remarks', 'like', 'Provincial monitoring demo project%')
            ->count();

        $this->command?->info(
            "Provincial project seeding complete. {$count} demo project(s) available."
        );
    }

    private function resolveTcUser(): User
    {
        $tc = User::query()
            ->where('role', UserRole::TC)
            ->where('is_active', true)
            ->first();

        if (! $tc) {
            throw new \RuntimeException(
                'No active TC account exists. Seed users first before running ProvincialProjectSeeder.'
            );
        }

        return $tc;
    }

    private function createOrUpdateDemoAdl(User $tc): Adl
    {
        $data = [
            'grants' => 30_000_000,
            'admin_cost' => 900_000,
            'total' => 30_900_000,
            'created_by' => $tc->id,
        ];

        /*
        |--------------------------------------------------------------------------
        | Phase 14 optional ADL columns
        |--------------------------------------------------------------------------
        */

        if (Schema::hasColumn('adls', 'date_received')) {
            $data['date_received'] = '2026-08-01';
        }

        if (Schema::hasColumn('adls', 'batch')) {
            $data['batch'] = 'Batch 01';
        }

        if (Schema::hasColumn('adls', 'tranche')) {
            $data['tranche'] = '1st Tranche';
        }

        if (Schema::hasColumn('adls', 'sponsor_reference')) {
            $data['sponsor_reference'] = 'DOLE Regional Office V';
        }

        if (Schema::hasColumn('adls', 'nfa_date')) {
            $data['nfa_date'] = '2026-08-01';
        }

        if (Schema::hasColumn('adls', 'nfa_number')) {
            $data['nfa_number'] = 'NFA-2026-DEMO-001';
        }

        if (Schema::hasColumn('adls', 'nta_date')) {
            $data['nta_date'] = '2026-08-05';
        }

        if (Schema::hasColumn('adls', 'nta_number')) {
            $data['nta_number'] = 'NTA-2026-DEMO-001';
        }

        return Adl::query()->updateOrCreate(
            [
                'adl_number' => 'ADL-2026-REGION-V-DEMO',
            ],
            $data
        );
    }

    private function createOrUpdateAllocation(
        Adl $adl,
        Province $province,
        User $tc
    ): AdlAllocation {
        /*
        |--------------------------------------------------------------------------
        | Give each province ₱5M.
        |--------------------------------------------------------------------------
        */

        $data = [
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => "Provincial Government of {$province->name}",
            'location' => $province->name,
            'amount' => 5_000_000,
            'remarks' => "Demo allocation for {$province->name}",
            'created_by' => $tc->id,
        ];

        /*
        |--------------------------------------------------------------------------
        | Phase 14 allocation fields, when available.
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'adl_allocations',
                'local_chief_executive_or_partylist'
            )
        ) {
            $data['local_chief_executive_or_partylist'] =
                "Provincial Government of {$province->name}";
        }

        if (
            Schema::hasColumn(
                'adl_allocations',
                'grant_amount'
            )
        ) {
            $data['grant_amount'] = 4_850_000;
        }

        if (
            Schema::hasColumn(
                'adl_allocations',
                'admin_cost_amount'
            )
        ) {
            $data['admin_cost_amount'] = 150_000;
        }

        if (
            Schema::hasColumn(
                'adl_allocations',
                'total_amount'
            )
        ) {
            $data['total_amount'] = 5_000_000;
        }

        return AdlAllocation::query()->updateOrCreate(
            [
                'adl_id' => $adl->id,
                'partner' =>
                    "Provincial Government of {$province->name}",
            ],
            $data
        );
    }

    private function createProject(
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
        AdlAllocation $allocation,
        User $tc,
        int $sequence,
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Give each project slightly different values.
        |--------------------------------------------------------------------------
        */

        $beneficiaries = match ($sequence) {
            1 => 50,
            2 => 75,
            default => 100,
        };

        $femaleBeneficiaries = match ($sequence) {
            1 => 22,
            2 => 38,
            default => 51,
        };

        $numberOfDays = match ($sequence) {
            1 => 10,
            2 => 15,
            default => 20,
        };

        $wageRate = 455.00;
        $insuranceRate = 50.00;

        $wagesTotal =
            $wageRate
            * $beneficiaries
            * $numberOfDays;

        $insuranceTotal =
            $insuranceRate
            * $beneficiaries;

        /*
        |--------------------------------------------------------------------------
        | Demo PPE amount.
        |--------------------------------------------------------------------------
        */

        $ppePerBeneficiary = 250.00;

        $ppeTotal =
            $ppePerBeneficiary
            * $beneficiaries;

        $totalProjectCost =
            $wagesTotal
            + $insuranceTotal
            + $ppeTotal;

        /*
        |--------------------------------------------------------------------------
        | Vary statuses so monitoring pages are meaningful.
        |--------------------------------------------------------------------------
        */

        $status = match ($sequence) {
            1 => ProjectStatus::ONGOING_PROFILING,
            2 => ProjectStatus::TSSD_EVALUATION,
            default => ProjectStatus::FOR_APPROVAL,
        };

        $projectTitle =
            "{$province->name} TUPAD Demo Project {$sequence}";

        $project = Project::query()->updateOrCreate(
            [
                'adl_allocation_id' => $allocation->id,
                'project_title' => $projectTitle,
            ],
            [
                'date_received' =>
                    now()
                        ->subDays(20 - ($sequence * 3))
                        ->toDateString(),

                'nature_of_work' =>
                    $this->natureOfWork($sequence),

                /*
                |--------------------------------------------------------------------------
                | Geographic reference IDs
                |--------------------------------------------------------------------------
                */

                'province_id' =>
                    $province->id,

                'municipality_id' =>
                    $municipality->id,

                'barangay_id' =>
                    $barangay->id,

                /*
                |--------------------------------------------------------------------------
                | Legacy/display location fields
                |--------------------------------------------------------------------------
                */

                'province' =>
                    $province->name,

                'district' =>
                    $municipality->district,

                'municipality' =>
                    $municipality->name,

                'barangay' =>
                    $barangay->name,

                'income_class' =>
                    $municipality->income_class,

                /*
                |--------------------------------------------------------------------------
                | Project implementation
                |--------------------------------------------------------------------------
                */

                'implementation_mode' =>
                    ImplementationMode::DIRECT_ADMINISTRATION,

                'number_of_days' =>
                    $numberOfDays,

                'term' =>
                    $numberOfDays <= 30
                        ? ProjectTerm::SHORT_TERM
                        : ProjectTerm::LONG_TERM,

                /*
                |--------------------------------------------------------------------------
                | Beneficiaries
                |--------------------------------------------------------------------------
                */

                'beneficiaries_total' =>
                    $beneficiaries,

                'beneficiaries_female' =>
                    $femaleBeneficiaries,

                /*
                |--------------------------------------------------------------------------
                | Financials
                |--------------------------------------------------------------------------
                */

                'wage_rate' =>
                    $wageRate,

                'wages_total' =>
                    $wagesTotal,

                'ppe_total' =>
                    $ppeTotal,

                'insurance_rate' =>
                    $insuranceRate,

                'insurance_total' =>
                    $insuranceTotal,

                'total_project_cost' =>
                    $totalProjectCost,

                /*
                |--------------------------------------------------------------------------
                | Workflow
                |--------------------------------------------------------------------------
                */

                'status' =>
                    $status,

                'remarks' =>
                    "Provincial monitoring demo project {$sequence} for {$province->name}.",

                'created_by' =>
                    $tc->id,

                'updated_by' =>
                    $tc->id,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | PPE Demo Row
        |--------------------------------------------------------------------------
        |
        | Create PPE through the relationship so the project's PPE table also
        | contains usable demo data.
        |
        */

        if (method_exists($project, 'ppeItems')) {
            $project->ppeItems()->updateOrCreate(
                [
                    'product' => 'Basic Protective Equipment',
                ],
                [
                    'ppe_type' => 'non_hazardous',
                    'beneficiary_count' => $beneficiaries,
                    'unit_amount' => $ppePerBeneficiary,
                    'total_amount' => $ppeTotal,
                ]
            );
        }
    }

    private function natureOfWork(int $sequence): string
    {
        return match ($sequence) {
            1 => 'Community Clean-Up and Environmental Rehabilitation',
            2 => 'Roadside Clearing and Public Area Maintenance',
            default => 'Drainage Clearing and Community Rehabilitation',
        };
    }
}