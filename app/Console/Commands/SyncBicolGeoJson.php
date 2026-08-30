<?php

namespace App\Console\Commands;

use App\Services\Mapping\BicolBoundarySyncService;
use App\Services\Mapping\BicolGeographicFoundation;
use Illuminate\Console\Command;
use Throwable;

class SyncBicolGeoJson extends Command
{
    protected $signature = 'tupad:mapping-sync-boundaries
        {--force : Replace an existing validated Region V GeoJSON set}';

    protected $description = 'Validate Bicol PSGC references and download PSGC-keyed Region V province/municipality GeoJSON.';

    public function handle(
        BicolGeographicFoundation $foundation,
        BicolBoundarySyncService $syncService,
    ): int {
        $issues = $foundation->validationIssues();

        if ($issues !== []) {
            $this->error('Bicol mapping reference data is not ready.');
            foreach ($issues as $issue) {
                $this->line(' - '.$issue);
            }
            $this->newLine();
            $this->warn('Run the reviewed FY2025 fresh-data seeder/reference-data process first. Do not create duplicate geographic rows.');

            return self::FAILURE;
        }

        $this->info('Bicol PSGC reference validation passed.');
        $this->line('Downloading only Region V province and per-province municipality boundaries...');

        try {
            $result = $syncService->sync((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bicol mapping boundary foundation is ready.');
        $this->table(
            ['Item', 'Result'],
            [
                ['Province polygons', (string) $result['province_features']],
                ['Municipality/city polygons', (string) $result['municipality_features']],
                ['Generated files', (string) $result['files']],
                ['Manifest', $result['manifest']],
            ],
        );
        $this->line('Join key: GeoJSON properties.psgc_code ↔ existing province/municipality code column.');

        return self::SUCCESS;
    }
}
