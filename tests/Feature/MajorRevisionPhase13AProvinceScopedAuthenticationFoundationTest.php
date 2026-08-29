<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MajorRevisionPhase13AProvinceScopedAuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private ProvinceAccessService $provinceAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provinceAccess = app(ProvinceAccessService::class);
    }

    public function test_users_have_nullable_assigned_province_reference(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'assigned_province_id'));

        $province = $this->province('Masbate', '054100000');
        $tc = $this->user(UserRole::TC, $province->id);

        $this->assertTrue($tc->requiresProvinceAssignment());
        $this->assertTrue($tc->hasAssignedProvince());
        $this->assertTrue($tc->assignedProvince->is($province));
        $this->assertTrue($province->assignedCoordinators()->whereKey($tc->id)->exists());
    }

    public function test_only_tupad_coordinators_are_province_scoped_at_the_foundation_layer(): void
    {
        $masbate = $this->province('Masbate', '054100000');
        $albay = $this->province('Albay', '050500000');

        $tc = $this->user(UserRole::TC, $masbate->id);
        $admin = $this->user(UserRole::ADMIN);
        $focal = $this->user(UserRole::FOCAL);
        $gip = $this->user(UserRole::GIP);

        $this->assertTrue($this->provinceAccess->isProvinceScoped($tc));
        $this->assertTrue($this->provinceAccess->canAccessProvince($tc, $masbate));
        $this->assertFalse($this->provinceAccess->canAccessProvince($tc, $albay));

        foreach ([$admin, $focal, $gip] as $regionalUser) {
            $this->assertFalse($this->provinceAccess->isProvinceScoped($regionalUser));
            $this->assertTrue($this->provinceAccess->canAccessProvince($regionalUser, $masbate));
            $this->assertTrue($this->provinceAccess->canAccessProvince($regionalUser, $albay));
        }
    }

    public function test_unassigned_tupad_coordinator_fails_closed_in_access_service(): void
    {
        $masbate = $this->province('Masbate', '054100000');
        $tc = $this->user(UserRole::TC);

        $this->assertNull($this->provinceAccess->assignedProvinceId($tc));
        $this->assertFalse($this->provinceAccess->canAccessProvince($tc, $masbate));
        $this->assertSame(0, $this->provinceAccess->scopeProvinces(Province::query(), $tc)->count());
    }

    public function test_project_and_draft_access_support_exact_reference_and_legacy_province_name(): void
    {
        $masbate = $this->province('Masbate', '054100000');
        $albay = $this->province('Albay', '050500000');
        $tc = $this->user(UserRole::TC, $masbate->id);

        $exactMasbateProject = new Project([
            'province_id' => $masbate->id,
            'province' => 'Masbate',
        ]);
        $albayProject = new Project([
            'province_id' => $albay->id,
            'province' => 'Albay',
        ]);
        $legacyMasbateProject = new Project([
            'province_id' => null,
            'province' => '  MASBATE  ',
        ]);
        $legacyMasbateDraft = new ProjectDraft([
            'province_id' => null,
            'province' => 'masbate',
        ]);

        $this->assertTrue($this->provinceAccess->canAccessProject($tc, $exactMasbateProject));
        $this->assertFalse($this->provinceAccess->canAccessProject($tc, $albayProject));
        $this->assertTrue($this->provinceAccess->canAccessProject($tc, $legacyMasbateProject));
        $this->assertTrue($this->provinceAccess->canAccessProjectDraft($tc, $legacyMasbateDraft));
    }

    public function test_assigned_province_query_returns_only_coordinators_province(): void
    {
        $masbate = $this->province('Masbate', '054100000');
        $this->province('Albay', '050500000');
        $tc = $this->user(UserRole::TC, $masbate->id);

        $visible = $this->provinceAccess
            ->scopeProvinces(Province::query()->orderBy('name'), $tc)
            ->pluck('name')
            ->all();

        $this->assertSame(['Masbate'], $visible);
    }

    public function test_coordinator_assignment_middleware_is_registered_and_fails_closed(): void
    {
        Route::middleware(['web', 'auth', 'province.assigned'])
            ->get('/_phase13a/province-assignment-check', fn () => response('ok'));

        $unassignedTc = $this->user(UserRole::TC);

        $this->autoAssignCoordinatorProvinceForTests = false;
        $this->actingAs($unassignedTc)
            ->get('/_phase13a/province-assignment-check')
            ->assertForbidden();
        $this->autoAssignCoordinatorProvinceForTests = true;

        $masbate = $this->province('Masbate', '054100000');
        $assignedTc = $this->user(UserRole::TC, $masbate->id);

        $this->actingAs($assignedTc)
            ->get('/_phase13a/province-assignment-check')
            ->assertOk()
            ->assertSee('ok');

        $focal = $this->user(UserRole::FOCAL);

        $this->actingAs($focal)
            ->get('/_phase13a/province-assignment-check')
            ->assertOk();
    }

    public function test_release_verifier_requires_the_new_user_province_column(): void
    {
        $this->artisan('tupad:release-verify')
            ->assertExitCode(0);
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function user(UserRole $role, ?int $provinceId = null): User
    {
        return User::factory()->create([
            'position' => $role->label(),
            'role' => $role,
            'is_active' => true,
            'assigned_province_id' => $provinceId,
        ]);
    }
}
