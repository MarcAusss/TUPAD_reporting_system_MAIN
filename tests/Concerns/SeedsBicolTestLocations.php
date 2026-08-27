<?php

namespace Tests\Concerns;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;

trait SeedsBicolTestLocations
{
    protected function seedBicolTestLocations(): void
    {
        $locations = [
            'Albay' => [
                'Tabaco City' => '1st District',
                'Daraga' => '2nd District',
                'Guinobatan' => '3rd District',
                'Legazpi City' => '2nd District',
            ],
            'Camarines Norte' => [
                'Daet' => '2nd District',
                'Labo' => '1st District',
                'Vinzons' => '2nd District',
            ],
            'Camarines Sur' => [
                'Naga City' => '3rd District',
                'Pili' => '3rd District',
            ],
            'Catanduanes' => [
                'Virac' => 'Lone District',
                'San Andres' => 'Lone District',
            ],
            'Masbate' => [
                'San Pascual' => '1st District',
                'Esperanza' => '3rd District',
                'Milagros' => '2nd District',
                'Balud' => '2nd District',
            ],
            'Sorsogon' => [
                'Sorsogon City' => '1st District',
            ],
        ];

        $provinceSequence = 1;

        foreach ($locations as $provinceName => $municipalities) {
            $province = Province::create([
                'code' => sprintf('05%07d', $provinceSequence),
                'name' => $provinceName,
                'is_active' => true,
            ]);

            $municipalitySequence = 1;

            foreach ($municipalities as $municipalityName => $district) {
                $municipality = Municipality::create([
                    'province_id' => $province->id,
                    'code' => sprintf(
                        '05%03d%04d',
                        $provinceSequence,
                        $municipalitySequence,
                    ),
                    'name' => $municipalityName,
                    'district' => $district,
                    'is_city' => str_contains($municipalityName, 'City'),
                    'is_active' => true,
                ]);

                for ($barangaySequence = 1; $barangaySequence <= 6; $barangaySequence++) {
                    Barangay::create([
                        'municipality_id' => $municipality->id,
                        'code' => sprintf(
                            '05%03d%04d%03d',
                            $provinceSequence,
                            $municipalitySequence,
                            $barangaySequence,
                        ),
                        'name' => sprintf('Barangay %02d', $barangaySequence),
                        'is_active' => true,
                    ]);
                }

                $municipalitySequence++;
            }

            $provinceSequence++;
        }
    }
}
