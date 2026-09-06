<?php

namespace Tests\Feature;

use Database\Seeders\Fy2025TupadProjectSeeder;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class CurrentSystemDemoSeederPsgcCompatibilityTest extends TestCase
{
    #[Test]
    public function fy2025_seeder_normalizes_psgc_city_of_and_common_city_suffix_names(): void
    {
        $reflection = new ReflectionClass(Fy2025TupadProjectSeeder::class);
        $method = $reflection->getMethod('normalizePlace');
        $seeder = new Fy2025TupadProjectSeeder();

        foreach ([
            ['City of Tabaco', 'Tabaco City'],
            ['City of Legazpi', 'Legazpi City'],
            ['City of Naga', 'Naga City'],
            ['City of Sorsogon', 'Sorsogon City'],
        ] as [$psgcName, $commonName]) {
            $this->assertSame(
                $method->invoke($seeder, $psgcName),
                $method->invoke($seeder, $commonName),
            );
        }
    }
}
