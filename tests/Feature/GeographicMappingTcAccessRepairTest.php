<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\CoordinatorProvinceAssignmentService;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographicMappingTcAccessRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_tc_missing_assignment_is_repaired_to_active_albay(): void
    {
        $albay = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'username' => 'tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => null,
        ]);

        $resolved = app(CoordinatorProvinceAssignmentService::class)
            ->resolve($tc, repair: true);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($albay));
        $this->assertSame($albay->id, $tc->fresh()->assigned_province_id);
    }

    public function test_non_bicol_or_inactive_assignment_is_not_considered_valid(): void
    {
        $foreign = Province::query()->create([
            'code' => '999999999',
            'name' => 'Outside Mapping Scope',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'username' => 'custom.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $foreign->id,
        ]);

        $this->assertNull(
            app(ProvinceAccessService::class)->assignedProvinceId($tc)
        );
    }

    public function test_explicit_repair_command_assigns_requested_active_bicol_province(): void
    {
        $masbate = Province::query()->create([
            'code' => '054100000',
            'name' => 'Masbate',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'username' => 'masbate.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => null,
        ]);

        $this->artisan('tupad:repair-tc-mapping-access', [
            '--username' => 'masbate.tc',
            '--province' => '054100000',
        ])->assertExitCode(0);

        $this->assertSame(
            $masbate->id,
            $tc->fresh()->assigned_province_id,
        );
    }
}
