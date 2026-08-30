<?php

namespace App\Console\Commands;

use App\Services\Mapping\BicolBarangayLabelSyncService;
use App\Services\Mapping\BicolGeographicFoundation;
use Illuminate\Console\Command;
use Throwable;

class SyncBicolBarangayLabels extends Command
{
    protected $signature = 'tupad:mapping-sync-barangay-labels
        {--force : Replace an existing reviewed barangay label GeoJSON set}';

    protected $description = 'Generate lazy per-municipality Bicol barangay label points from reviewed PSGC boundary GeoJSON.';

    public function handle(
        BicolGeographicFoundation $foundation,
        BicolBarangayLabelSyncService $syncService,
    ): int {
        $issues = $foundation->validationIssues();

        if ($issues !== []) {
            $this->error('Bicol mapping reference data is not ready.');
            foreach ($issues as $issue) {
                $this->line(' - '.$issue);
            }

            return self::FAILURE;
        }

        $this->info('Bicol PSGC reference validation passed.');
        $this->line('Generating one lightweight barangay-label GeoJSON file per municipality/city...');

        try {
            $result = $syncService->sync((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Bicol barangay label layer is ready.');
        $this->table(
            ['Item', 'Result'],
            [
                ['Municipalities/cities', (string) $result['municipalities']],
                ['Barangay labels', (string) $result['barangay_labels']],
                ['Skipped: source geometry unavailable', (string) $result['unavailable_labels']],
                ['Generated files', (string) $result['files']],
                ['Directory', $result['directory']],
            ],
        );
        if ($result['unavailable_labels'] > 0) {
            $this->warn(
                'Some valid barangays were omitted from the optional label layer because the reviewed source has no usable geometry. '.
                'No coordinates were guessed. PSGC code(s): '.implode(', ', $result['unavailable_psgc_codes'])
            );
        }

        $this->line('Runtime map loading remains lazy: only the selected municipality label file is fetched.');

        return self::SUCCESS;
    }
}
