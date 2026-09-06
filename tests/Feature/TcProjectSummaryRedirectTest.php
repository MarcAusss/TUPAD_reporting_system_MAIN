<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TcProjectSummaryRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_project_summary_index_redirects_to_assigned_province(): void
    {
        $province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);

        $this->actingAs($tc)
            ->get(route('project-summary.index'))
            ->assertRedirect(route('project-summary.province', $province));
    }

    public function test_tc_cannot_open_another_province_summary(): void
    {
        $assigned = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $foreign = Province::create([
            'code' => '051600000',
            'name' => 'Camarines Norte',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $assigned->id,
        ]);

        $this->actingAs($tc)
            ->get(route('project-summary.province', $foreign))
            ->assertForbidden();
    }

    public function test_focal_project_summary_index_keeps_the_regional_selector(): void
    {
        Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this->actingAs($focal)
            ->get(route('project-summary.index'))
            ->assertOk();
    }
}
