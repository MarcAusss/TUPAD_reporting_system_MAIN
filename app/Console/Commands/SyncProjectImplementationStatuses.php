<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Console\Command;

class SyncProjectImplementationStatuses extends Command
{
    protected $signature = 'projects:sync-implementation-statuses';

    protected $description =
        'Synchronize project statuses based on implementation dates.';

    public function handle(): int
    {
        $today = today();

        $projects = Project::query()
            ->whereIn(
                'status',
                [
                    ProjectStatus::FOR_IMPLEMENTATION->value,
                    ProjectStatus::ONGOING_IMPLEMENTATION->value,
                ]
            )
            ->with('implementation')
            ->get();

        $updated = 0;

        foreach ($projects as $project) {
            if (!$project->implementation) {
                continue;
            }

            $start = $project->implementation->start_date;
            $end = $project->implementation->end_date;

            /*
            |--------------------------------------------------------------------------
            | Implementation Finished
            |--------------------------------------------------------------------------
            */

            if ($today->gt($end)) {
                if (
                    $project->status
                    !== ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
                ) {
                    $project->update([
                        'status' =>
                            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    ]);

                    $updated++;
                }

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Implementation Has Started
            |--------------------------------------------------------------------------
            */

            if (
                $today->gte($start)
                && $today->lte($end)
            ) {
                if (
                    $project->status
                    !== ProjectStatus::ONGOING_IMPLEMENTATION
                ) {
                    $project->update([
                        'status' =>
                            ProjectStatus::ONGOING_IMPLEMENTATION,
                    ]);

                    $updated++;
                }
            }
        }

        $this->info(
            "{$updated} project status(es) updated."
        );

        return self::SUCCESS;
    }
}