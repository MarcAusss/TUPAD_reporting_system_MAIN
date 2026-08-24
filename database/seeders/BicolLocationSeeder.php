<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;

class BicolLocationSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Albay' => [
                [
                    'name' => 'Legazpi City',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => true,
                    'barangays' => [
                        'Bagumbayan',
                        'Banquerohan',
                        'Bitano',
                    ],
                ],
                [
                    'name' => 'Daraga',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Anislag',
                        'Bañadero',
                        'Busay',
                    ],
                ],
                [
                    'name' => 'Tabaco City',
                    'district' => '1st District',
                    'income_class' => '4th Class',
                    'is_city' => true,
                    'barangays' => [
                        'Bacolod',
                        'Basagan',
                        'Bombon',
                    ],
                ],
            ],

            'Camarines Norte' => [
                [
                    'name' => 'Daet',
                    'district' => '1st District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Alawihao',
                        'Awitan',
                        'Bagasbas',
                    ],
                ],
                [
                    'name' => 'Jose Panganiban',
                    'district' => '1st District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Bagong Bayan',
                        'Dahican',
                        'Larap',
                    ],
                ],
                [
                    'name' => 'Labo',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Anahaw',
                        'Bautista',
                        'Bayabas',
                    ],
                ],
            ],

            'Camarines Sur' => [
                [
                    'name' => 'Naga City',
                    'district' => '3rd District',
                    'income_class' => '1st Class',
                    'is_city' => true,
                    'barangays' => [
                        'Abella',
                        'Bagumbayan Norte',
                        'Concepcion Grande',
                    ],
                ],
                [
                    'name' => 'Iriga City',
                    'district' => '5th District',
                    'income_class' => '4th Class',
                    'is_city' => true,
                    'barangays' => [
                        'Antipolo',
                        'San Francisco',
                        'San Isidro',
                    ],
                ],
                [
                    'name' => 'Pili',
                    'district' => '3rd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Anayan',
                        'Cadlan',
                        'San Agustin',
                    ],
                ],
            ],

            'Catanduanes' => [
                [
                    'name' => 'Virac',
                    'district' => 'Lone District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Mabini',
                        'San Isidro Village',
                        'Rawis',
                    ],
                ],
                [
                    'name' => 'San Andres',
                    'district' => 'Lone District',
                    'income_class' => '3rd Class',
                    'is_city' => false,
                    'barangays' => [
                        'Belmonte',
                        'Codon',
                        'Lictin',
                    ],
                ],
                [
                    'name' => 'Baras',
                    'district' => 'Lone District',
                    'income_class' => '5th Class',
                    'is_city' => false,
                    'barangays' => [
                        'Agban',
                        'Benticayan',
                        'Osmeña',
                    ],
                ],
            ],

            'Masbate' => [
                [
                    'name' => 'Masbate City',
                    'district' => '2nd District',
                    'income_class' => '4th Class',
                    'is_city' => true,
                    'barangays' => [
                        'Bapor',
                        'Cagba',
                        'Centro',
                    ],
                ],
                [
                    'name' => 'Aroroy',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Ambolong',
                        'Balawing',
                        'Cabangcalan',
                    ],
                ],
                [
                    'name' => 'Milagros',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'Bangad',
                        'Cayabon',
                        'Poblacion East',
                    ],
                ],
            ],

            'Sorsogon' => [
                [
                    'name' => 'Sorsogon City',
                    'district' => '1st District',
                    'income_class' => '3rd Class',
                    'is_city' => true,
                    'barangays' => [
                        'Abuyog',
                        'Balogo',
                        'Bitas',
                    ],
                ],
                [
                    'name' => 'Bulan',
                    'district' => '2nd District',
                    'income_class' => '1st Class',
                    'is_city' => false,
                    'barangays' => [
                        'A. Bonifacio',
                        'Beguin',
                        'Zone 1',
                    ],
                ],
                [
                    'name' => 'Gubat',
                    'district' => '2nd District',
                    'income_class' => '2nd Class',
                    'is_city' => false,
                    'barangays' => [
                        'Bagacay',
                        'Balud Del Norte',
                        'Cogon',
                    ],
                ],
            ],
        ];

        foreach ($data as $provinceName => $municipalities) {
            $province = Province::query()->updateOrCreate(
                [
                    'name' => $provinceName,
                ],
                [
                    'is_active' => true,
                ]
            );

            foreach ($municipalities as $municipalityData) {
                $municipality = Municipality::query()->updateOrCreate(
                    [
                        'province_id' => $province->id,
                        'name' => $municipalityData['name'],
                    ],
                    [
                        'district' => $municipalityData['district'],
                        'income_class' => $municipalityData['income_class'],
                        'is_city' => $municipalityData['is_city'],
                        'is_active' => true,
                    ]
                );

                foreach ($municipalityData['barangays'] as $barangayName) {
                    Barangay::query()->updateOrCreate(
                        [
                            'municipality_id' => $municipality->id,
                            'name' => $barangayName,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        $this->command?->info(
            'Bicol geographic reference data seeded successfully.'
        );
    }
}