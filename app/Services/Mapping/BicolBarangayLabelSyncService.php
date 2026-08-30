<?php

namespace App\Services\Mapping;

use App\Models\Municipality;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

final class BicolBarangayLabelSyncService
{
    public function __construct(
        private readonly BicolGeographicFoundation $foundation,
        private readonly HttpFactory $http,
        private readonly Filesystem $files,
    ) {}

    /** @return array{municipalities:int,barangay_labels:int,unavailable_labels:int,unavailable_psgc_codes:array<int,string>,files:int,directory:string} */
    public function sync(bool $force = false): array
    {
        $issues = $this->foundation->validationIssues();

        if ($issues !== []) {
            throw new RuntimeException("Bicol PSGC reference validation failed:\n- ".implode("\n- ", $issues));
        }

        $target = $this->foundation->barangayLabelDirectory();

        if (! $force && $this->files->isDirectory($target)) {
            throw new RuntimeException(
                'Bicol barangay label GeoJSON already exists. Use --force only when intentionally refreshing the reviewed source.'
            );
        }

        $tempRoot = storage_path('app/tmp/tupad-bicol-barangay-labels-'.bin2hex(random_bytes(6)));
        $this->files->makeDirectory($tempRoot, 0755, true);

        $municipalityCount = 0;
        $barangayCount = 0;
        $manifestFiles = [];
        $unavailableLabels = [];

        try {
            foreach ($this->foundation->provinces() as $province) {
                foreach ($this->foundation->municipalitiesForProvince($province) as $municipality) {
                    $sourceCode = $this->foundation->sourceMunicipalityCode((string) $municipality->code);
                    $url = sprintf(
                        (string) config('tupad_mapping.boundary_source.barangay_url_pattern'),
                        $sourceCode,
                    );
                    $source = $this->downloadJson($url);
                    $normalized = $this->normalizeBarangayLabelFeatures($source, $municipality);
                    $features = $normalized['features'];
                    $unavailableLabels = array_merge($unavailableLabels, $normalized['unavailable']);
                    $fileName = (string) $municipality->code.'.geojson';
                    $path = $tempRoot.DIRECTORY_SEPARATOR.$fileName;

                    $this->writeFeatureCollection($path, $features);

                    $municipalityCount++;
                    $barangayCount += count($features);
                    $manifestFiles[$fileName] = $this->fileManifest($path, count($features));
                }
            }

            $manifest = [
                'schema_version' => 1,
                'generated_at' => now()->toIso8601String(),
                'purpose' => 'Leaflet permanent barangay labels only; municipality polygons remain the visible map boundary.',
                'source' => [
                    'provider' => (string) config('tupad_mapping.boundary_source.provider'),
                    'basis' => (string) config('tupad_mapping.boundary_source.source_basis'),
                    'resolution' => (string) config('tupad_mapping.boundary_source.resolution'),
                    'attribution' => (string) config('tupad_mapping.boundary_source.attribution'),
                ],
                'join_key' => 'properties.psgc_code',
                'geometry' => 'Point (bounding-box center of reviewed barangay polygon)',
                'unavailable_geometry' => array_values($unavailableLabels),
                'files' => $manifestFiles,
            ];

            $this->files->put(
                $tempRoot.DIRECTORY_SEPARATOR.'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            );

            if ($this->files->isDirectory($target)) {
                $this->files->deleteDirectory($target);
            }

            $parent = dirname($target);
            if (! $this->files->isDirectory($parent)) {
                $this->files->makeDirectory($parent, 0755, true);
            }

            if (! $this->files->copyDirectory($tempRoot, $target)) {
                if ($this->files->isDirectory($target)) {
                    $this->files->deleteDirectory($target);
                }

                throw new RuntimeException('Unable to publish Bicol barangay label GeoJSON files to '.$target.'.');
            }

            foreach (array_keys($manifestFiles) as $fileName) {
                if (! $this->files->exists($target.DIRECTORY_SEPARATOR.$fileName)) {
                    $this->files->deleteDirectory($target);
                    throw new RuntimeException('Published Bicol barangay label GeoJSON is incomplete; missing '.$fileName.'.');
                }
            }

            if (! $this->files->exists($target.DIRECTORY_SEPARATOR.'manifest.json')) {
                $this->files->deleteDirectory($target);
                throw new RuntimeException('Published Bicol barangay label GeoJSON is missing its manifest.');
            }

            $this->files->deleteDirectory($tempRoot);

            return [
                'municipalities' => $municipalityCount,
                'barangay_labels' => $barangayCount,
                'unavailable_labels' => count($unavailableLabels),
                'unavailable_psgc_codes' => array_values(array_keys($unavailableLabels)),
                'files' => count($manifestFiles) + 1,
                'directory' => $target,
            ];
        } catch (\Throwable $e) {
            if ($this->files->isDirectory($tempRoot)) {
                $this->files->deleteDirectory($tempRoot);
            }

            throw $e;
        }
    }

    private function downloadJson(string $url): array
    {
        if ($url === '') {
            throw new RuntimeException('Barangay boundary source URL pattern is not configured.');
        }

        $response = $this->http
            ->retry(3, 750)
            ->timeout(60)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download barangay boundary source {$url} (HTTP {$response->status()}).");
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'FeatureCollection' || ! is_array($decoded['features'] ?? null)) {
            throw new RuntimeException("Barangay boundary source {$url} is not a valid GeoJSON FeatureCollection.");
        }

        return $decoded;
    }

    /**
     * @return array{
     *     features:array<int,array<string,mixed>>,
     *     unavailable:array<string,array<string,string>>
     * }
     */
    private function normalizeBarangayLabelFeatures(array $source, Municipality $municipality): array
    {
        $barangays = $this->foundation->barangaysForMunicipality($municipality)->keyBy('code');
        $expectedCodes = $barangays->keys()->all();
        $features = [];
        $matchedCodes = [];
        $unavailable = [];

        foreach ($source['features'] as $feature) {
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $code = $this->featureCode($properties, $expectedCodes);

            if (! $code || ! $barangays->has($code)) {
                continue;
            }

            // The PSGC row exists in the reviewed source even when that source
            // has no geometry for the feature. Track that distinction so a
            // source-data geometry gap does not look like a missing PSGC row.
            $matchedCodes[$code] = true;

            $barangay = $barangays->get($code);
            $center = $this->geometryBoundingBoxCenter($feature['geometry'] ?? null);

            if ($center === null) {
                $unavailable[$code] = [
                    'psgc_code' => $code,
                    'name' => (string) $barangay->name,
                    'municipality_psgc_code' => (string) $municipality->code,
                    'municipality_name' => (string) $municipality->name,
                    'reason' => 'Reviewed barangay boundary source has no usable polygon geometry; no coordinate was inferred.',
                ];

                continue;
            }

            $features[$code] = [
                'type' => 'Feature',
                'properties' => [
                    'psgc_code' => $code,
                    'name' => (string) $barangay->name,
                    'level' => 'barangay-label',
                    'municipality_psgc_code' => (string) $municipality->code,
                    'municipality_name' => (string) $municipality->name,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $center,
                ],
            ];
        }

        // Still fail closed when the reviewed source does not contain an
        // expected PSGC row at all. Only matched rows with unusable geometry
        // are allowed to be omitted from the optional label layer.
        $missing = array_diff($expectedCodes, array_keys($matchedCodes));
        if ($missing !== []) {
            throw new RuntimeException(
                "Barangay boundary source for {$municipality->name} is missing current PSGC code(s): ".implode(', ', $missing).'.'
            );
        }

        ksort($features);
        ksort($unavailable);

        return [
            'features' => array_values($features),
            'unavailable' => $unavailable,
        ];
    }

    private function featureCode(array $properties, array $expectedCodes): ?string
    {
        foreach (['adm4_psgc', 'ADM4_PCODE', 'psgc_code', 'PSGC', 'PCODE', 'code'] as $key) {
            $candidate = $this->foundation->normalizePsgcCode($properties[$key] ?? null);
            if ($candidate && in_array($candidate, $expectedCodes, true)) {
                return $candidate;
            }
        }

        foreach ($properties as $value) {
            $candidate = $this->foundation->normalizePsgcCode($value);
            if ($candidate && in_array($candidate, $expectedCodes, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array{0:float,1:float}|null */
    private function geometryBoundingBoxCenter(mixed $geometry): ?array
    {
        if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }

        $points = [];
        $this->collectCoordinatePairs($geometry['coordinates'] ?? null, $points);

        if ($points === []) {
            return null;
        }

        $longitudes = array_column($points, 0);
        $latitudes = array_column($points, 1);

        return [
            (min($longitudes) + max($longitudes)) / 2,
            (min($latitudes) + max($latitudes)) / 2,
        ];
    }

    /** @param array<int,array{0:float,1:float}> $points */
    private function collectCoordinatePairs(mixed $value, array &$points): void
    {
        if (! is_array($value)) {
            return;
        }

        if (
            count($value) >= 2
            && is_numeric($value[0] ?? null)
            && is_numeric($value[1] ?? null)
        ) {
            $points[] = [(float) $value[0], (float) $value[1]];
            return;
        }

        foreach ($value as $child) {
            $this->collectCoordinatePairs($child, $points);
        }
    }

    private function writeFeatureCollection(string $path, array $features): void
    {
        $this->files->put(
            $path,
            json_encode(
                ['type' => 'FeatureCollection', 'features' => $features],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL,
        );
    }

    /** @return array{feature_count:int,sha256:string,bytes:int} */
    private function fileManifest(string $path, int $featureCount): array
    {
        return [
            'feature_count' => $featureCount,
            'sha256' => hash_file('sha256', $path),
            'bytes' => (int) filesize($path),
        ];
    }
}
