<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\Fy2025TupadProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshDatabaseBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_baseline_schema_contains_current_core_tables(): void
    {
        foreach ([
            'users',
            'provinces',
            'municipalities',
            'barangays',
            'adls',
            'adl_allocations',
            'projects',
            'project_locations',
            'project_location_barangay',
            'project_beneficiary_sectors',
            'project_disbursements',
            'project_acp_payments',
            'project_acp_check_releases',
            'project_acp_liquidations',
            'audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing baseline table: {$table}");
        }

        $this->assertTrue(Schema::hasColumn('users', 'assigned_province_id'));
        $this->assertTrue(Schema::hasColumn('projects', 'province_id'));
        $this->assertTrue(Schema::hasColumn('projects', 'insurance_beneficiaries'));
        $this->assertTrue(Schema::hasColumn('project_location_barangay', 'beneficiaries_total'));
        $this->assertTrue(Schema::hasColumn('project_location_barangay', 'beneficiaries_female'));
    }

    public function test_fy2025_seeder_rebuilds_reference_users_and_thirty_ongoing_projects(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $this->assertDatabaseCount('provinces', 6);
        $this->assertDatabaseCount('municipalities', 114);
        $this->assertDatabaseCount('barangays', 3465);
        $this->assertDatabaseCount('projects', 30);

        foreach ([
            'Albay',
            'Camarines Norte',
            'Camarines Sur',
            'Catanduanes',
            'Masbate',
            'Sorsogon',
        ] as $province) {
            $this->assertSame(5, Project::query()->where('province', $province)->count());
        }

        $this->assertSame(
            30,
            Project::query()->where('status', ProjectStatus::ONGOING_PROFILING->value)->count(),
        );

        $tc = User::query()->where('username', 'tc')->firstOrFail();
        $this->assertSame(UserRole::TC, $tc->role);
        $this->assertSame('050500000', $tc->assignedProvince?->code);

        foreach (['admin', 'focal', 'gip'] as $username) {
            $this->assertTrue(User::query()->where('username', $username)->exists());
        }
    }
}
