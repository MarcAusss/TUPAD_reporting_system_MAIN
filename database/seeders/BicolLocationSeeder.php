<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BicolLocationSeeder extends Seeder
{
    private const API = 'https://psgc.gitlab.io/api';
    private const REGION_CODE = '050000000';

    public function run(): void
    {
        $this->command?->info('Downloading complete Bicol PSGC reference data...');

        $provinces = $this->getJson(
            self::API.'/regions/'.self::REGION_CODE.'/provinces/'
        );

        if (count($provinces) !== 6) {
            throw new RuntimeException(
                'Expected 6 Bicol provinces; PSGC returned '.count($provinces).'.'
            );
        }

        foreach ($provinces as $provinceData) {
            $province = Province::query()->updateOrCreate(
                ['name' => $provinceData['name']],
                [
                    'code' => $provinceData['code'] ?? null,
                    'is_active' => true,
                ]
            );

            $municipalities = $this->getJson(
                self::API
                .'/provinces/'
                .$provinceData['code']
                .'/cities-municipalities/'
            );

            foreach ($municipalities as $municipalityData) {
                $district = $this->districtFor(
                    $provinceData['name'],
                    $municipalityData['name']
                );

                if (! $district) {
                    throw new RuntimeException(
                        "Missing legislative district mapping for {$municipalityData['name']}, {$provinceData['name']}."
                    );
                }

                $municipality = Municipality::query()->updateOrCreate(
                    [
                        'province_id' => $province->id,
                        'name' => $municipalityData['name'],
                    ],
                    [
                        'code' => $municipalityData['code'] ?? null,
                        'district' => $district,
                        'income_class' => null,
                        'is_city' => (bool) ($municipalityData['isCity'] ?? false),
                        'is_active' => true,
                    ]
                );

                $barangays = $this->getJson(
                    self::API
                    .'/cities-municipalities/'
                    .$municipalityData['code']
                    .'/barangays/'
                );

                foreach ($barangays as $barangayData) {
                    Barangay::query()->updateOrCreate(
                        [
                            'municipality_id' => $municipality->id,
                            'name' => $barangayData['name'],
                        ],
                        [
                            'code' => $barangayData['code'] ?? null,
                            'is_active' => true,
                        ]
                    );
                }
            }

            $this->command?->info(
                "Seeded {$province->name}: ".count($municipalities).' municipalities/cities.'
            );
        }

        $this->command?->info('Complete Bicol PSGC seeding finished.');
    }

    private function getJson(string $url): array
    {
        $response = Http::retry(3, 750)
            ->timeout(45)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Unable to download PSGC data from {$url} (HTTP {$response->status()})."
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException("Unexpected PSGC response from {$url}.");
        }

        return $data;
    }

    private function districtFor(string $province, string $municipality): ?string
    {
        $map = $this->districtMap();

        return $map[$this->normalize($province)]
            [$this->normalize($municipality)] ?? null;
    }

    private function normalize(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace('city of ', '')
            ->replace(' city', '')
            ->replace('ñ', 'n')
            ->replace('á', 'a')
            ->replace('é', 'e')
            ->replace('í', 'i')
            ->replace('ó', 'o')
            ->replace('ú', 'u')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function mapDistricts(array $districts): array
    {
        $result = [];

        foreach ($districts as $district => $municipalities) {
            foreach ($municipalities as $municipality) {
                $result[$this->normalize($municipality)] = $district;
            }
        }

        return $result;
    }

    private function districtMap(): array
    {
        return [
            'albay' => $this->mapDistricts([
                '1st District' => ['Bacacay','Malinao','Malilipot','Santo Domingo','Tabaco City','Tiwi'],
                '2nd District' => ['Camalig','Daraga','Legazpi City','Manito','Rapu-Rapu'],
                '3rd District' => ['Guinobatan','Jovellar','Libon','Ligao City','Oas','Pio Duran','Polangui'],
            ]),
            'camarines norte' => $this->mapDistricts([
                '1st District' => ['Capalonga','Jose Panganiban','Labo','Paracale','Santa Elena'],
                '2nd District' => ['Basud','Daet','Mercedes','San Lorenzo Ruiz','San Vicente','Talisay','Vinzons'],
            ]),
            'camarines sur' => $this->mapDistricts([
                '1st District' => ['Del Gallego','Ragay','Lupi','Sipocot','Cabusao'],
                '2nd District' => ['Libmanan','Minalabac','Pamplona','Pasacao','San Fernando','Gainza','Milaor'],
                '3rd District' => ['Naga City','Pili','Ocampo','Camaligan','Canaman','Magarao','Bombon','Calabanga'],
                '4th District' => ['Caramoan','Garchitorena','Goa','Lagonoy','Presentacion','Sagñay','San Jose','Tigaon','Tinambac','Siruma'],
                '5th District' => ['Iriga City','Baao','Balatan','Bato','Buhi','Bula','Nabua'],
            ]),
            'catanduanes' => $this->mapDistricts([
                'Lone District' => ['Bagamanoc','Baras','Bato','Caramoran','Gigmoto','Pandan','Panganiban','San Andres','San Miguel','Viga','Virac'],
            ]),
            'masbate' => $this->mapDistricts([
                '1st District' => ['San Pascual','Claveria','Monreal','San Jacinto','San Fernando','Batuan'],
                '2nd District' => ['Masbate City','Mobo','Milagros','Aroroy','Baleno','Balud','Mandaon'],
                '3rd District' => ['Uson','Dimasalang','Palanas','Cataingan','Pio V. Corpuz','Esperanza','Placer','Cawayan'],
            ]),
            'sorsogon' => $this->mapDistricts([
                '1st District' => ['Sorsogon City','Pilar','Donsol','Castilla','Casiguran','Magallanes'],
                '2nd District' => ['Barcelona','Prieto Diaz','Gubat','Juban','Bulusan','Irosin','Santa Magdalena','Matnog','Bulan'],
            ]),
        ];
    }
}
