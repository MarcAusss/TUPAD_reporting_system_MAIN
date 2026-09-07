<?php

namespace App\Services\Release;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BicolReferenceDataService
{
    /**
     * Synchronize the reviewed local Region V geography into the canonical
     * province/municipality/barangay tables. This method creates no users,
     * ADLs, projects, payments, or demonstration records.
     *
     * @return array{provinces:int,municipalities:int,barangays:int}
     */
    public function sync(): array
    {
        $definitions = (array) config('tupad_mapping.provinces', []);

        if (count($definitions) !== 6) {
            throw new RuntimeException('Expected exactly six reviewed Bicol province definitions.');
        }

        $manifest = $this->readGeoJson(public_path('geojson/bicol/barangay-labels/manifest.json'));
        $unavailableByMunicipality = collect($manifest['unavailable_geometry'] ?? [])
            ->groupBy('municipality_psgc_code');

        $municipalityCount = 0;
        $barangayCount = 0;

        DB::transaction(function () use ($definitions, $unavailableByMunicipality, &$municipalityCount, &$barangayCount): void {
            foreach ($definitions as $provinceCode => $definition) {
                $provinceName = (string) ($definition['name'] ?? '');
                $slug = (string) ($definition['slug'] ?? '');

                if ($provinceName === '' || $slug === '') {
                    throw new RuntimeException("Invalid Region V province definition for {$provinceCode}.");
                }

                $province = Province::query()->updateOrCreate(
                    ['code' => (string) $provinceCode],
                    ['name' => $provinceName, 'is_active' => true],
                );

                $municipalityGeoJson = $this->readGeoJson(
                    public_path("geojson/bicol/municipalities/{$slug}.geojson")
                );

                foreach (($municipalityGeoJson['features'] ?? []) as $feature) {
                    $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
                    $municipalityCode = trim((string) ($properties['psgc_code'] ?? ''));
                    $municipalityName = trim((string) ($properties['name'] ?? ''));

                    if ($municipalityCode === '' || $municipalityName === '') {
                        throw new RuntimeException("Invalid municipality feature in {$provinceName} reference data.");
                    }

                    $district = $this->districtFor($provinceName, $municipalityName);
                    if ($district === null) {
                        throw new RuntimeException("Missing district mapping for {$municipalityName}, {$provinceName}.");
                    }

                    $municipality = Municipality::query()->updateOrCreate(
                        ['code' => $municipalityCode],
                        [
                            'province_id' => $province->id,
                            'name' => $municipalityName,
                            'district' => $district,
                            'is_city' => (bool) ($properties['is_city'] ?? false),
                            'is_active' => true,
                        ],
                    );
                    $municipalityCount++;

                    $barangayRows = [];
                    $barangayPath = public_path("geojson/bicol/barangay-labels/{$municipalityCode}.geojson");
                    $barangayGeoJson = $this->readGeoJson($barangayPath);

                    foreach (($barangayGeoJson['features'] ?? []) as $barangayFeature) {
                        $barangayProperties = is_array($barangayFeature['properties'] ?? null)
                            ? $barangayFeature['properties']
                            : [];
                        $barangayCode = trim((string) ($barangayProperties['psgc_code'] ?? ''));
                        $barangayName = trim((string) ($barangayProperties['name'] ?? ''));

                        if ($barangayCode !== '' && $barangayName !== '') {
                            $barangayRows[$barangayCode] = $barangayName;
                        }
                    }

                    foreach ($unavailableByMunicipality->get($municipalityCode, collect()) as $unavailable) {
                        $barangayCode = trim((string) ($unavailable['psgc_code'] ?? ''));
                        $barangayName = trim((string) ($unavailable['name'] ?? ''));
                        if ($barangayCode !== '' && $barangayName !== '') {
                            $barangayRows[$barangayCode] = $barangayName;
                        }
                    }

                    if ($barangayRows === []) {
                        throw new RuntimeException("No reviewed barangay rows found for {$municipalityName} ({$municipalityCode}).");
                    }

                    foreach ($barangayRows as $barangayCode => $barangayName) {
                        Barangay::query()->updateOrCreate(
                            ['code' => $barangayCode],
                            [
                                'municipality_id' => $municipality->id,
                                'name' => $barangayName,
                                'is_active' => true,
                            ],
                        );
                        $barangayCount++;
                    }
                }
            }
        });

        return [
            'provinces' => count($definitions),
            'municipalities' => $municipalityCount,
            'barangays' => $barangayCount,
        ];
    }

    private function readGeoJson(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException("Required reviewed geographic reference file is missing: {$path}");
        }

        $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid geographic reference file: {$path}");
        }

        return $decoded;
    }

    private function districtFor(string $province, string $municipality): ?string
    {
        return $this->districtMap()[$this->normalizePlace($province)][$this->normalizePlace($municipality)] ?? null;
    }

    private function normalizePlace(string $name): string
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

    /** @return array<string,string> */
    private function mapDistricts(array $districts): array
    {
        $result = [];
        foreach ($districts as $district => $municipalities) {
            foreach ($municipalities as $municipality) {
                $result[$this->normalizePlace($municipality)] = $district;
            }
        }

        return $result;
    }

    /** @return array<string,array<string,string>> */
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
