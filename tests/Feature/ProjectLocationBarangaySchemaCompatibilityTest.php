<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectLocationBarangaySchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_location_barangay_has_exact_beneficiary_allocation_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'project_location_barangay',
                'beneficiaries_total'
            )
        );

        $this->assertTrue(
            Schema::hasColumn(
                'project_location_barangay',
                'beneficiaries_female'
            )
        );
    }
}
