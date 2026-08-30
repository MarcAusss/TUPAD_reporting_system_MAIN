<?php

namespace App\Services\Mapping;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Support\Collection;

final class BicolGeographicFoundation
{
    public function regionCode(): string
    {
        return (string) config('tupad_mapping.region.code', '050000000');
    }

    /** @return array<string, array{name:string,slug:string,source_code:string}> */
    public function provinceDefinitions(): array
    {
        return (array) config('tupad_mapping.provinces', []);
    }

    /** @return Collection<int, Province> */
    public function provinces(): Collection
    {
        $codes = array_keys($this->provinceDefinitions());

        return Province::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function provinceById(int $provinceId): ?Province
    {
        $codes = array_keys($this->provinceDefinitions());

        return Province::query()
            ->whereKey($provinceId)
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->first();
    }

    /** @return Collection<int, Municipality> */
    public function municipalitiesForProvince(int|Province $province): Collection
    {
        $provinceId = $province instanceof Province
            ? (int) $province->id
            : $province;

        return Municipality::query()
            ->where('province_id', $provinceId)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();
    }

    public function municipalityById(int $provinceId, int $municipalityId): ?Municipality
    {
        $province = $this->provinceById($provinceId);

        if (! $province) {
            return null;
        }

        return Municipality::query()
            ->whereKey($municipalityId)
            ->where('province_id', $province->id)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->first();
    }

    /** @return Collection<int, Barangay> */
    public function barangaysForMunicipality(int|Municipality $municipality): Collection
    {
        $municipalityId = $municipality instanceof Municipality
            ? (int) $municipality->id
            : $municipality;

        return Barangay::query()
            ->where('municipality_id', $municipalityId)
            ->where('is_active', true)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();
    }

    public function municipalityBoundaryRelativePath(string $provinceCode): string
    {
        $definition = $this->provinceDefinitions()[$provinceCode] ?? null;

        if (! $definition) {
            throw new \InvalidArgumentException("Unknown Bicol province PSGC code {$provinceCode}.");
        }

        return trim((string) config('tupad_mapping.public_path', 'geojson/bicol'), '/\\')
            .'/municipalities/'.$definition['slug'].'.geojson';
    }

    public function barangayLabelRelativePath(string $municipalityCode): string
    {
        $canonical = $this->normalizePsgcCode($municipalityCode);

        if (! $canonical) {
            throw new \InvalidArgumentException("Invalid municipality PSGC code {$municipalityCode}.");
        }

        return trim((string) config('tupad_mapping.public_path', 'geojson/bicol'), '/\\')
            .'/barangay-labels/'.$canonical.'.geojson';
    }

    /**
     * Convert this project's canonical 9-digit PSGC correspondence code to the
     * numeric-looking source filename used by the reviewed 2023 GIS provider.
     * Example: 050501000 -> 500501000.
     */
    public function sourceMunicipalityCode(string $municipalityCode): string
    {
        $canonical = $this->normalizePsgcCode($municipalityCode);

        if (! $canonical) {
            throw new \InvalidArgumentException("Invalid municipality PSGC code {$municipalityCode}.");
        }

        $tenDigit = substr($canonical, 0, 2).'0'.substr($canonical, 2);

        return ltrim($tenDigit, '0');
    }

    /**
     * Validate the existing reference data needed to join database rows to
     * polygons. This deliberately fails closed instead of falling back to
     * location-name matching.
     *
     * @return array<int, string>
     */
    public function validationIssues(): array
    {
        $issues = [];
        $definitions = $this->provinceDefinitions();
        $expectedCodes = array_keys($definitions);

        if (count($expectedCodes) !== 6) {
            $issues[] = 'Mapping configuration must define exactly six Region V provinces.';
        }

        $provinces = Province::query()
            ->whereIn('code', $expectedCodes)
            ->get()
            ->keyBy('code');

        foreach ($definitions as $code => $definition) {
            $province = $provinces->get($code);

            if (! $province) {
                $issues[] = "Missing PSGC province reference {$code} ({$definition['name']}).";
                continue;
            }

            if (! $province->is_active) {
                $issues[] = "Province {$province->name} ({$code}) is inactive.";
            }

            $missingMunicipalityCodes = Municipality::query()
                ->where('province_id', $province->id)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->count();

            if ($missingMunicipalityCodes > 0) {
                $issues[] = "Province {$province->name} has {$missingMunicipalityCodes} active municipality/city record(s) without PSGC code.";
            }

            $missingBarangayCodes = Barangay::query()
                ->whereHas('municipality', fn ($query) => $query->where('province_id', $province->id))
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->count();

            if ($missingBarangayCodes > 0) {
                $issues[] = "Province {$province->name} has {$missingBarangayCodes} active barangay record(s) without PSGC code.";
            }
        }

        $duplicateMunicipalityCodes = Municipality::query()
            ->whereNotNull('code')
            ->whereIn('province_id', $provinces->pluck('id'))
            ->selectRaw('code, COUNT(*) AS aggregate_count')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        if ($duplicateMunicipalityCodes->isNotEmpty()) {
            $issues[] = 'Duplicate municipality/city PSGC code(s): '.$duplicateMunicipalityCodes->implode(', ').'.';
        }

        $duplicateBarangayCodes = Barangay::query()
            ->whereNotNull('code')
            ->whereHas('municipality', fn ($query) => $query->whereIn('province_id', $provinces->pluck('id')))
            ->selectRaw('code, COUNT(*) AS aggregate_count')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        if ($duplicateBarangayCodes->isNotEmpty()) {
            $issues[] = 'Duplicate barangay PSGC code(s): '.$duplicateBarangayCodes->implode(', ').'.';
        }

        return $issues;
    }

    public function normalizePsgcCode(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // Some GIS exports serialize a 10-digit PSGC while this project
        // stores the official 9-digit correspondence code used by the
        // existing PSGC API seeder. Drop the third digit to convert the
        // 10-digit representation (e.g. 0500500000) to 050500000.
        if (strlen($digits) === 10) {
            $digits = substr($digits, 0, 2).substr($digits, 3);
        }

        // Source filenames may have lost the leading zero after numeric
        // coercion (e.g. 500500000). Rehydrate that form before applying
        // the same correspondence-code conversion.
        if (strlen($digits) === 9 && ! str_starts_with($digits, '0')) {
            $tenDigit = '0'.$digits;
            $digits = substr($tenDigit, 0, 2).substr($tenDigit, 3);
        }

        return strlen($digits) === 9 ? $digits : null;
    }

    public function publicDirectory(): string
    {
        $relative = trim((string) config('tupad_mapping.public_path', 'geojson/bicol'), '/\\');

        return public_path($relative);
    }

    public function regionBoundaryPath(): string
    {
        return $this->publicDirectory().DIRECTORY_SEPARATOR.'provinces.geojson';
    }

    public function municipalityBoundaryPath(string $provinceCode): string
    {
        $definition = $this->provinceDefinitions()[$provinceCode] ?? null;

        if (! $definition) {
            throw new \InvalidArgumentException("Unknown Bicol province PSGC code {$provinceCode}.");
        }

        return $this->publicDirectory()
            .DIRECTORY_SEPARATOR.'municipalities'
            .DIRECTORY_SEPARATOR.$definition['slug'].'.geojson';
    }

    public function barangayLabelDirectory(): string
    {
        return $this->publicDirectory().DIRECTORY_SEPARATOR.'barangay-labels';
    }

    public function barangayLabelPath(string $municipalityCode): string
    {
        $canonical = $this->normalizePsgcCode($municipalityCode);

        if (! $canonical) {
            throw new \InvalidArgumentException("Invalid municipality PSGC code {$municipalityCode}.");
        }

        return $this->barangayLabelDirectory().DIRECTORY_SEPARATOR.$canonical.'.geojson';
    }
}
