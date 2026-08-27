<?php

namespace App\Console\Commands;

use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Console\Command;

class SyncProjectImplementationStatuses extends Command
{
    protected $signature = 'projects:sync-implementation-statuses';

    protected $description =
        'Compatibility alias for the consolidated project status engine.';

    public function handle(ProjectStatusEngine $statusEngine): int
    {
        $result = $statusEngine->synchronizeEligible();

        $this->info(sprintf(
            '%d eligible project(s) scanned; %d project status(es) updated.',
            $result['scanned'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
