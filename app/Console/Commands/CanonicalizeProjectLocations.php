<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Projects\ProjectLocationCanonicalService;
use Illuminate\Console\Command;
use Throwable;

class CanonicalizeProjectLocations extends Command
{
    protected $signature = 'projects:canonicalize-locations
        {--dry-run : Inspect and report required repairs without changing data}';

    protected $description =
        'Make project_locations the canonical official-project geography and synchronize legacy project location snapshots.';

    public function handle(ProjectLocationCanonicalService $locations): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [
            'already_consistent' => 0,
            'snapshot_repaired' => 0,
            'canonical_created' => 0,
            'unresolved' => 0,
        ];

        $this->info($dryRun
            ? 'Inspecting canonical project locations (DRY RUN).'
            : 'Canonicalizing official project locations.');

        Project::query()->orderBy('id')->each(function (Project $project) use ($locations, $dryRun, &$results): void {
            if ($project->projectLocations()->exists()) {
                try {
                    $locations->assertProjectIntegrity($project);
                    $results['already_consistent']++;
                    return;
                } catch (Throwable $exception) {
                    // A compatibility-only mismatch can be repaired from the
                    // canonical rows. Invalid canonical hierarchy remains blocked.
                    try {
                        $snapshot = $locations->compatibilitySnapshot($project);

                        if ($snapshot === null) {
                            throw $exception;
                        }

                        if ($dryRun) {
                            $project->forceFill($snapshot);
                            $locations->assertProjectIntegrity($project);
                        } else {
                            $locations->synchronizeCompatibilitySnapshot($project);
                            $locations->assertProjectIntegrity($project->fresh());
                        }

                        $results['snapshot_repaired']++;
                        $this->line(sprintf(
                            'Project #%d: %s compatibility snapshot synchronized.',
                            $project->id,
                            $dryRun ? 'would be' : 'was',
                        ));

                        return;
                    } catch (Throwable $repairException) {
                        $results['unresolved']++;
                        $this->error("Project #{$project->id}: {$repairException->getMessage()}");
                        return;
                    }
                }
            }

            $resolved = $locations->resolveLegacyLocation($project);

            if ($resolved === null) {
                $results['unresolved']++;
                $this->error("Project #{$project->id}: legacy location could not be resolved.");
                return;
            }

            if (! $dryRun) {
                $locations->createSingleCanonicalLocation(
                    $project,
                    $resolved['province'],
                    $resolved['municipality'],
                    $resolved['barangay'],
                );
            }

            $results['canonical_created']++;
            $this->line(sprintf(
                'Project #%d: canonical location %s from the legacy reference.',
                $project->id,
                $dryRun ? 'would be created' : 'created',
            ));
        });

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Already consistent', $results['already_consistent']],
                [$dryRun ? 'Would repair snapshot' : 'Snapshot repaired', $results['snapshot_repaired']],
                [$dryRun ? 'Would create canonical location' : 'Canonical location created', $results['canonical_created']],
                ['Unresolved / invalid', $results['unresolved']],
            ],
        );

        if ($results['unresolved'] > 0) {
            $this->error('Canonicalization completed with unresolved records. Correct them before release.');
            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Dry run completed. No records were modified.'
            : 'Canonical project-location synchronization completed.');

        return self::SUCCESS;
    }
}
