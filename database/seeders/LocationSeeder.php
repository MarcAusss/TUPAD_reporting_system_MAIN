<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Catanduanes
        |--------------------------------------------------------------------------
        |
        | Development data only.
        | We will replace/expand this with official geographic reference data.
        |
        */

        $catanduanes = Province::updateOrCreate(
            [
                'name' => 'Catanduanes',
            ],
            [
                'code' => 'CAT',
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Virac
        |--------------------------------------------------------------------------
        */

        $virac = Municipality::updateOrCreate(
            [
                'province_id' => $catanduanes->id,
                'name' => 'Virac',
            ],
            [
                'district' => 'Lone District',
                'income_class' => null,
                'is_city' => false,
                'is_active' => true,
            ]
        );

        foreach ([
            'Mabini',
            'San Isidro Village',
            'San Vicente',
            'San Rafael',
            'Santiago',
        ] as $barangayName) {
            Barangay::updateOrCreate(
                [
                    'municipality_id' => $virac->id,
                    'name' => $barangayName,
                ],
                [
                    'is_active' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Caramoran
        |--------------------------------------------------------------------------
        */

        $caramoran = Municipality::updateOrCreate(
            [
                'province_id' => $catanduanes->id,
                'name' => 'Caramoran',
            ],
            [
                'district' => 'Lone District',
                'income_class' => null,
                'is_city' => false,
                'is_active' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Same barangay name intentionally seeded
        |--------------------------------------------------------------------------
        |
        | This verifies that:
        |
        | Virac / Mabini
        | and
        | Caramoran / Mabini
        |
        | are separate records.
        |
        */

        Barangay::updateOrCreate(
            [
                'municipality_id' =>
                    $caramoran->id,

                'name' =>
                    'Mabini',
            ],
            [
                'is_active' =>
                    true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | San Andres
        |--------------------------------------------------------------------------
        */

        $sanAndres = Municipality::updateOrCreate(
            [
                'province_id' =>
                    $catanduanes->id,

                'name' =>
                    'San Andres',
            ],
            [
                'district' =>
                    'Lone District',

                'income_class' =>
                    null,

                'is_city' =>
                    false,

                'is_active' =>
                    true,
            ]
        );

        Barangay::updateOrCreate(
            [
                'municipality_id' =>
                    $sanAndres->id,

                'name' =>
                    'Belmonte',
            ],
            [
                'is_active' =>
                    true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Baras
        |--------------------------------------------------------------------------
        */

        $baras = Municipality::updateOrCreate(
            [
                'province_id' =>
                    $catanduanes->id,

                'name' =>
                    'Baras',
            ],
            [
                'district' =>
                    'Lone District',

                'income_class' =>
                    null,

                'is_city' =>
                    false,

                'is_active' =>
                    true,
            ]
        );

        Barangay::updateOrCreate(
            [
                'municipality_id' =>
                    $baras->id,

                'name' =>
                    'Poblacion',
            ],
            [
                'is_active' =>
                    true,
            ]
        );
    }
}