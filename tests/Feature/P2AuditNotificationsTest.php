<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P2AuditNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_and_read_audit_trail_while_focal_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'updated',
            'module' => 'User Management',
            'auditable_type' => User::class,
            'auditable_id' => $focal->id,
            'old_values' => ['position' => 'Old Position'],
            'new_values' => ['position' => 'New Position'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'performed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('audit.index', ['module' => 'User Management', 'action' => 'updated']))
            ->assertOk()
            ->assertSee('Audit Trail')
            ->assertSee('User Management')
            ->assertSee('position')
            ->assertDontSee('New Position');

        $this->actingAs($focal)->get(route('audit.index'))->assertForbidden();
    }

    public function test_tc_notification_center_surfaces_official_tssd_queue_and_topbar_link(): void
    {
        $province = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);
        $adl = Adl::create([
            'adl_number' => 'AUDIT-NOTIF-001',
            'grants' => 100000,
            'admin_cost' => 0,
            'total' => 100000,
            'created_by' => $focal->id,
        ]);
        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => 100000,
            'created_by' => $focal->id,
        ]);
        Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'TSSD Queue Notification Project',
            'nature_of_work' => 'Community clean-up',
            'province' => 'Albay',
            'district' => '1st District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'province_id' => $province->id,
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wage_rate' => 400,
            'wages_total' => 40000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 500,
            'total_project_cost' => 40500,
            'status' => ProjectStatus::TSSD_EVALUATION,
            'created_by' => $tc->id,
        ]);

        $this->actingAs($tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('notifications.index'), false);

        $this->actingAs($tc)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('System Attention Center')
            ->assertSee('TSSD Evaluation')
            ->assertSee('Open Queue');
    }

    public function test_admin_navigation_exposes_audit_trail_while_focal_navigation_does_not(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Audit Trail');
        $this->actingAs($focal)->get(route('dashboard'))->assertOk()->assertDontSee('Audit Trail');
    }
}
