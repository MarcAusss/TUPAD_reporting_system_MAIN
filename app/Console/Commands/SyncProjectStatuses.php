<?php

namespace App\Console\Commands;

use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Console\Command;

class SyncProjectStatuses extends Command
{
    protected $signature = 'projects:sync-statuses
        {--project=* : Synchronize only the specified project ID(s)}';

    protected $description =
        'Synchronize deterministic project workflow statuses from authoritative records.';

    public function handle(ProjectStatusEngine $statusEngine): int
    {
        $requestedIds = $this->option('project');

        $projectIds = $requestedIds === []
            ? null
            : array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                $requestedIds,
            )));

        if (
            $projectIds !== null
            && collect($projectIds)->contains(
                static fn (int $id): bool => $id < 1,
            )
        ) {
            $this->error('Every --project value must be a positive project ID.');

            return self::FAILURE;
        }

        $result = $statusEngine->synchronizeEligible(
            projectIds: $projectIds,
        );

        $this->info(sprintf(
            '%d eligible project(s) scanned; %d project status(es) updated.',
            $result['scanned'],
            $result['updated'],
        ));

        return self::SUCCESS;
    }
}
