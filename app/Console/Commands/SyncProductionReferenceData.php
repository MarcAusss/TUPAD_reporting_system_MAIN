<?php

namespace App\Console\Commands;

use App\Services\Release\BicolReferenceDataService;
use Illuminate\Console\Command;
use Throwable;

class SyncProductionReferenceData extends Command
{
    protected $signature = 'tupad:reference-data-sync';

    protected $description = 'Idempotently load reviewed Region V PSGC reference data without creating demo users or projects.';

    public function handle(BicolReferenceDataService $service): int
    {
        $this->info('Synchronizing reviewed Region V reference data...');

        try {
            $result = $service->sync();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Reference set', 'Rows synchronized'], [
            ['Provinces', (string) $result['provinces']],
            ['Municipalities/Cities', (string) $result['municipalities']],
            ['Barangays', (string) $result['barangays']],
        ]);
        $this->info('Reference-data synchronization completed. No demo operational records were created.');

        return self::SUCCESS;
    }
}
