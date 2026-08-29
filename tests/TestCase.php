<?php

namespace Tests;

use App\Models\Province;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Phase 13D compatibility for legacy feature tests created before province-
     * scoped Coordinator accounts existed. Production code never auto-assigns a
     * province; this only upgrades old test fixtures to the new invariant.
     */
    protected bool $autoAssignCoordinatorProvinceForTests = true;

    public function actingAs(Authenticatable $user, $guard = null)
    {
        if (
            $this->autoAssignCoordinatorProvinceForTests
            && $user instanceof User
            && $user->exists
            && $user->isTc()
            && ! $user->hasAssignedProvince()
            && Schema::hasTable('provinces')
            && Schema::hasColumn('users', 'assigned_province_id')
        ) {
            $province = Province::query()
                ->where('name', 'Albay')
                ->first()
                ?? Province::query()->where('is_active', true)->first()
                ?? Province::query()->create([
                    'code' => '050500000',
                    'name' => 'Albay',
                    'is_active' => true,
                ]);

            $user->forceFill([
                'assigned_province_id' => $province->id,
            ])->saveQuietly();
        }

        return parent::actingAs($user, $guard);
    }
}
