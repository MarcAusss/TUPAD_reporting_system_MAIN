<?php

namespace Tests\Feature;

use Database\Seeders\CurrentSystemDemoSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class CurrentSystemDemoSeederPsgcCompatibilityTest extends TestCase
{
    #[Test]
    public function demo_seeder_normalizes_psgc_city_of_and_common_city_suffix_names(): void
    {
        $reflection =
            new ReflectionClass(
                CurrentSystemDemoSeeder::class
            );

        $method =
            $reflection->getMethod(
                'normalizeMunicipalityName'
            );

        $seeder =
            new CurrentSystemDemoSeeder();

        $this->assertSame(
            $method->invoke(
                $seeder,
                'City of Tabaco'
            ),
            $method->invoke(
                $seeder,
                'Tabaco City'
            )
        );

        $this->assertSame(
            $method->invoke(
                $seeder,
                'City of Legazpi'
            ),
            $method->invoke(
                $seeder,
                'Legazpi City'
            )
        );

        $this->assertSame(
            $method->invoke(
                $seeder,
                'City of Naga'
            ),
            $method->invoke(
                $seeder,
                'Naga City'
            )
        );

        $this->assertSame(
            $method->invoke(
                $seeder,
                'City of Sorsogon'
            ),
            $method->invoke(
                $seeder,
                'Sorsogon City'
            )
        );
    }
}
