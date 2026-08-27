<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\CurrentSystemDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsBicolTestLocations;
use Tests\TestCase;

class MajorRevisionPhase11ReleaseVerificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsBicolTestLocations;

    public function test_release_verifier_passes_for_consistent_current_data(): void
    {
        $this->createConsistentProject();

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Release verification PASSED')
            ->assertExitCode(0);
    }

    public function test_release_verifier_blocks_overallocated_adl_data(): void
    {
        ['adl' => $adl, 'user' => $user] = $this->createConsistentProject();

        AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Second Partner',
            'location' => 'Bicol Region',
            'amount' => '1000.00',
            'grant_amount' => '1000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000.00',
            'created_by' => $user->id,
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('is over-allocated')
            ->assertExitCode(1);
    }

    public function test_release_verifier_blocks_incomplete_exact_geographic_allocations(): void
    {
        ['project' => $project] = $this->createConsistentProject();

        DB::table('project_location_barangay')
            ->whereIn(
                'project_location_id',
                $project->projectLocations()->pluck('id')
            )
            ->update([
                'beneficiaries_total' => null,
                'beneficiaries_female' => null,
            ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('incomplete exact barangay beneficiary allocation')
            ->assertExitCode(1);
    }

    public function test_production_verification_blocks_known_development_passwords(): void
    {
        $this->createConsistentProject();

        User::factory()->create([
            'name' => 'Unsafe Release User',
            'username' => 'unsafe-release-user',
            'email' => 'unsafe-release-user@example.test',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->artisan('tupad:release-verify', ['--production' => true])
            ->expectsOutputToContain('development password "password"')
            ->assertExitCode(1);
    }

    public function test_default_database_seeder_is_no_op_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('adls', 0);
    }

    public function test_demo_seeder_reconciles_funds_project_codes_and_exact_geography(): void
    {
        $this->seedBicolTestLocations();
        $this->seed(UserSeeder::class);
        $this->seed(CurrentSystemDemoSeeder::class);

        $adl = Adl::query()
            ->where('adl_number', 'ADL-2026-CURRENT-DEMO')
            ->firstOrFail();

        $this->assertSame('95000000.00', $adl->grants);
        $this->assertSame(
            9_500_000_000,
            $this->moneyToCents($adl->allocations()->sum('amount')),
        );
        $this->assertSame(
            $this->moneyToCents($adl->grants),
            $this->moneyToCents($adl->allocations()->sum('amount')),
        );

        $this->assertEqualsCanonicalizing(
            [
                'Albay',
                'Camarines Norte',
                'Camarines Sur',
                'Catanduanes',
                'Masbate',
                'Sorsogon',
            ],
            Project::query()
                ->distinct()
                ->pluck('province')
                ->all(),
        );

        $codes = DB::table('project_approvals')
            ->whereNotNull('project_code')
            ->pluck('project_code')
            ->all();

        $this->assertContains('TUPAD-ALB-2026-001', $codes);
        $this->assertContains('TUPAD-CAN-2026-001', $codes);
        $this->assertContains('TUPAD-CAT-2026-001', $codes);
        $this->assertContains('TUPAD-MAS-2026-001', $codes);
        $this->assertContains('TUPAD-SOR-2026-001', $codes);
        $this->assertCount(count(array_unique($codes)), $codes);

        Project::query()
            ->where('remarks', 'like', '%demo%')
            ->orWhere('remarks', 'like', '%demonstration%')
            ->get()
            ->each(function (Project $project): void {
                $rows = DB::table('project_locations as pl')
                    ->join(
                        'project_location_barangay as plb',
                        'plb.project_location_id',
                        '=',
                        'pl.id'
                    )
                    ->where('pl.project_id', $project->id)
                    ->select([
                        'plb.beneficiaries_total',
                        'plb.beneficiaries_female',
                    ])
                    ->get();

                $this->assertNotEmpty($rows);
                $this->assertFalse(
                    $rows->contains(
                        fn ($row): bool =>
                            $row->beneficiaries_total === null
                            || $row->beneficiaries_female === null
                    )
                );

                $this->assertSame(
                    (int) $project->beneficiaries_total,
                    (int) $rows->sum('beneficiaries_total'),
                );
                $this->assertSame(
                    (int) $project->beneficiaries_female,
                    (int) $rows->sum('beneficiaries_female'),
                );
            });
    }

    /**
     * @return array{adl: Adl, user: User, project: Project}
     */
    private function createConsistentProject(): array
    {
        $user = User::factory()->create([
            'name' => 'Phase 11 TC',
            'username' => 'phase11-tc',
            'email' => 'phase11-tc@example.test',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('safe-test-password'),
        ]);

        $province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $municipality = Municipality::create([
            'province_id' => $province->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'code' => '050501001',
            'name' => 'Barangay 1',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-PHASE11-001',
            'grants' => '5000.00',
            'admin_cost' => '0.00',
            'total' => '5000.00',
            'created_by' => $user->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi',
            'location' => 'Legazpi City, Albay',
            'province' => 'Albay',
            'municipality' => 'Legazpi City',
            'amount' => '5000.00',
            'grant_amount' => '5000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '5000.00',
            'created_by' => $user->id,
        ]);

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 11 Release Verification Project',
            'nature_of_work' => 'Release verification.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi',
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Barangay 1',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '455.00',
            'wages_total' => '1000.00',
            'ppe_total' => '100.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '50.00',
            'total_project_cost' => '1150.00',
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $user->id,
        ]);

        $location = ProjectLocation::create([
            'project_id' => $project->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);

        $location->barangays()->sync([
            $barangay->id => [
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 6,
            ],
        ]);

        return compact('adl', 'user', 'project');
    }

    private function moneyToCents(mixed $amount): int
    {
        $normalized = trim((string) $amount);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
