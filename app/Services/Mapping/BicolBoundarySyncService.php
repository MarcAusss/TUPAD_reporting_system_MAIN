<?php

namespace App\Services\Mapping;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

final class BicolBoundarySyncService
{
    public function __construct(
        private readonly BicolGeographicFoundation $foundation,
        private readonly HttpFactory $http,
        private readonly Filesystem $files,
    ) {}

    /** @return array{province_features:int,municipality_features:int,files:int,manifest:string} */
    public function sync(bool $force = false): array
    {
        $issues = $this->foundation->validationIssues();

        if ($issues !== []) {
            throw new RuntimeException("Bicol PSGC reference validation failed:\n- ".implode("\n- ", $issues));
        }

        $target = $this->foundation->publicDirectory();
        $regionPath = $this->foundation->regionBoundaryPath();

        if (! $force && $this->files->exists($regionPath)) {
            throw new RuntimeException('Bicol GeoJSON already exists. Use --force only after reviewing the replacement source.');
        }

        $tempRoot = storage_path('app/tmp/tupad-bicol-geojson-'.bin2hex(random_bytes(6)));
        $tempMunicipalities = $tempRoot.DIRECTORY_SEPARATOR.'municipalities';
        $this->files->makeDirectory($tempMunicipalities, 0755, true);

        try {
            $regionSource = $this->downloadJson((string) config('tupad_mapping.boundary_source.region_url'));
            $provinceFeatures = $this->normalizeProvinceFeatures($regionSource);
            $this->writeFeatureCollection($tempRoot.DIRECTORY_SEPARATOR.'provinces.geojson', $provinceFeatures);

            $municipalityTotal = 0;
            $manifestFiles = [
                'provinces.geojson' => $this->fileManifest($tempRoot.DIRECTORY_SEPARATOR.'provinces.geojson', count($provinceFeatures)),
            ];

            foreach ($this->foundation->provinceDefinitions() as $provinceCode => $definition) {
                $url = sprintf(
                    (string) config('tupad_mapping.boundary_source.municipality_url_pattern'),
                    $definition['source_code'],
                );
                $source = $this->downloadJson($url);
                $features = $this->normalizeMunicipalityFeatures($source, $provinceCode);
                $file = $tempMunicipalities.DIRECTORY_SEPARATOR.$definition['slug'].'.geojson';
                $this->writeFeatureCollection($file, $features);
                $municipalityTotal += count($features);
                $manifestFiles['municipalities/'.$definition['slug'].'.geojson'] = $this->fileManifest($file, count($features));
            }

            $manifest = [
                'schema_version' => 1,
                'region' => [
                    'psgc_code' => $this->foundation->regionCode(),
                    'name' => (string) config('tupad_mapping.region.name'),
                ],
                'generated_at' => now()->toIso8601String(),
                'source' => [
                    'provider' => (string) config('tupad_mapping.boundary_source.provider'),
                    'basis' => (string) config('tupad_mapping.boundary_source.source_basis'),
                    'resolution' => (string) config('tupad_mapping.boundary_source.resolution'),
                    'attribution' => (string) config('tupad_mapping.boundary_source.attribution'),
                ],
                'join_key' => 'properties.psgc_code',
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

            // Do not rely on moveDirectory() here. On Windows/PHP combinations
            // it may return without throwing even when the directory rename does
            // not result in the expected published files. Copy the fully validated
            // temporary tree, verify the published output, then remove the temp
            // directory. This also keeps the command fail-closed on partial copies.
            if (! $this->files->copyDirectory($tempRoot, $target)) {
                if ($this->files->isDirectory($target)) {
                    $this->files->deleteDirectory($target);
                }

                throw new RuntimeException('Unable to publish validated Bicol GeoJSON files to '.$target.'.');
            }

            $requiredPublishedFiles = array_merge(
                ['provinces.geojson', 'manifest.json'],
                array_map(
                    static fn (array $definition): string => 'municipalities/'.$definition['slug'].'.geojson',
                    array_values($this->foundation->provinceDefinitions()),
                ),
            );

            foreach ($requiredPublishedFiles as $relativeFile) {
                $publishedPath = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);

                if (! $this->files->exists($publishedPath)) {
                    $this->files->deleteDirectory($target);

                    throw new RuntimeException('Published Bicol GeoJSON is incomplete; missing '.$relativeFile.'.');
                }
            }

            $this->files->deleteDirectory($tempRoot);

            return [
                'province_features' => count($provinceFeatures),
                'municipality_features' => $municipalityTotal,
                'files' => count($manifestFiles) + 1,
                'manifest' => $target.DIRECTORY_SEPARATOR.'manifest.json',
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
        $response = $this->http
            ->retry(3, 750)
            ->timeout(60)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download mapping boundary source {$url} (HTTP {$response->status()}).");
        }

        $decoded = $response->json();

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== 'FeatureCollection' || ! is_array($decoded['features'] ?? null)) {
            throw new RuntimeException("Boundary source {$url} is not a valid GeoJSON FeatureCollection.");
        }

        return $decoded;
    }

    /** @return array<int, array<string,mixed>> */
    private function normalizeProvinceFeatures(array $source): array
    {
        $definitions = $this->foundation->provinceDefinitions();
        $models = Province::query()
            ->whereIn('code', array_keys($definitions))
            ->get()
            ->keyBy('code');
        $features = [];

        foreach ($source['features'] as $feature) {
            $code = $this->featureCode($feature, array_keys($definitions), ['ADM2_PCODE', 'psgc_code', 'PSGC', 'PCODE', 'code']);

            if (! $code || ! isset($definitions[$code])) {
                continue;
            }

            $model = $models->get($code);
            $features[$code] = $this->normalizedFeature(
                $feature,
                [
                    'psgc_code' => $code,
                    'name' => $model?->name ?? $definitions[$code]['name'],
                    'level' => 'province',
                ],
            );
        }

        $missing = array_diff(array_keys($definitions), array_keys($features));
        if ($missing !== []) {
            throw new RuntimeException('Province boundary source is missing PSGC code(s): '.implode(', ', $missing).'.');
        }

        ksort($features);

        return array_values($features);
    }

    /** @return array<int, array<string,mixed>> */
    private function normalizeMunicipalityFeatures(array $source, string $provinceCode): array
    {
        $province = Province::query()->where('code', $provinceCode)->firstOrFail();
        $municipalities = Municipality::query()
            ->where('province_id', $province->id)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->get()
            ->keyBy('code');
        $expectedCodes = $municipalities->keys()->all();
        $features = [];

        foreach ($source['features'] as $feature) {
            $code = $this->featureCode($feature, $expectedCodes, ['ADM3_PCODE', 'psgc_code', 'PSGC', 'PCODE', 'code']);

            if (! $code || ! $municipalities->has($code)) {
                continue;
            }

            $model = $municipalities->get($code);
            $features[$code] = $this->normalizedFeature(
                $feature,
                [
                    'psgc_code' => $code,
                    'name' => $model->name,
                    'level' => 'municipality',
                    'province_psgc_code' => $provinceCode,
                    'province_name' => $province->name,
                    'is_city' => (bool) $model->is_city,
                ],
            );
        }

        $missing = array_diff($expectedCodes, array_keys($features));
        if ($missing !== []) {
            throw new RuntimeException(
                "Municipality boundary source for {$province->name} is missing current PSGC code(s): ".implode(', ', $missing).'.'
            );
        }

        ksort($features);

        return array_values($features);
    }

    private function featureCode(array $feature, array $expectedCodes, array $preferredKeys): ?string
    {
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

        foreach ($preferredKeys as $key) {
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

    private function normalizedFeature(array $feature, array $properties): array
    {
        $geometry = $feature['geometry'] ?? null;
        $type = is_array($geometry) ? ($geometry['type'] ?? null) : null;

        if (! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            throw new RuntimeException('Mapping boundary feature must contain Polygon or MultiPolygon geometry.');
        }

        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => $geometry,
        ];
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
