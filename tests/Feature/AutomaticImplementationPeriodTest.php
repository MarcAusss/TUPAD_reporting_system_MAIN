<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticImplementationPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
    }

    public function test_twenty_day_project_calculates_end_date_twenty_days_after_start_date(): void
    {
        $project =
            $this->createApprovedProject(
                duration: 20
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $project
                ),
                [
                    'start_date' => '2026-08-25',
                    'remarks' => 'Approved schedule.',
                ]
            )
            ->assertRedirect();

        $project->refresh();

        $this->assertSame(
            '2026-08-25',
            $project
                ->implementation
                ->start_date
                ->toDateString()
        );

        $this->assertSame(
            '2026-09-14',
            $project
                ->implementation
                ->end_date
                ->toDateString()
        );
    }

    public function test_end_date_uses_each_projects_own_duration(): void
    {
        $tenDay =
            $this->createApprovedProject(
                duration: 10
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $tenDay
                ),
                [
                    'start_date' => '2026-08-01',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            '2026-08-11',
            $tenDay
                ->fresh()
                ->implementation
                ->end_date
                ->toDateString()
        );

        $thirtyDay =
            $this->createApprovedProject(
                duration: 30
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $thirtyDay
                ),
                [
                    'start_date' => '2026-08-01',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            '2026-08-31',
            $thirtyDay
                ->fresh()
                ->implementation
                ->end_date
                ->toDateString()
        );
    }

    public function test_manipulated_end_date_is_ignored_by_server(): void
    {
        $project =
            $this->createApprovedProject(
                duration: 20
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $project
                ),
                [
                    'start_date' => '2026-08-25',

                    // Must never override the calculated value.
                    'end_date' => '2099-12-31',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            '2026-09-14',
            $project
                ->fresh()
                ->implementation
                ->end_date
                ->toDateString()
        );
    }

    public function test_project_detail_shows_readonly_automatic_end_date(): void
    {
        $project =
            $this->createApprovedProject(
                duration: 20
            );

        $response =
            $this
                ->actingAs($this->tc)
                ->get(
                    route(
                        'projects.show',
                        $project
                    )
                );

        $response->assertOk();

        $response->assertSee(
            'Automatic End Date'
        );

        $response->assertSee(
            'data-duration-days="20"',
            false
        );

        $response->assertSee(
            'id="implementation-end-date"',
            false
        );

        $response->assertSee(
            'readonly',
            false
        );
    }

    private function createApprovedProject(
        int $duration
    ): Project {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' =>
                'ADL-R8-'.uniqid(),

            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation =
            AdlAllocation::create([
                'adl_id' => $adl->id,
                'fund_sponsor' => null,
                'partner' => null,
                'location' => 'Albay',
                'amount' => 1000000,
                'created_by' => $focal->id,
            ]);

        return Project::create([
            'adl_allocation_id' =>
                $allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                "R8 {$duration}-Day Project ".uniqid(),

            'nature_of_work' =>
                'Community clean-up',

            'fund_sponsor' =>
                'DOLE Regional Office V',

            'partner' =>
                'LGU Albay',

            'project_series' =>
                'Regular TUPAD 2026',

            'tevs_date_verified' =>
                now()->toDateString(),

            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',

            'implementation_mode' =>
                'direct_administration',

            'number_of_days' =>
                $duration,

            'term' => 'short_term',

            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,

            'wage_rate' => 455,
            'wages_total' =>
                50 * $duration * 455,

            'ppe_total' => 0,

            'insurance_rate' => 50,
            'insurance_total' => 2500,

            'total_project_cost' =>
                (50 * $duration * 455) + 2500,

            'status' =>
                ProjectStatus::FOR_IMPLEMENTATION,

            'created_by' =>
                $this->tc->id,
        ]);
    }
}
