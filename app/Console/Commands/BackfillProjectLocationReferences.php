<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Models\Province;
use Illuminate\Console\Command;

class BackfillProjectLocationReferences extends Command
{
    protected $signature = 'projects:backfill-locations
        {--dry-run : Show what would be changed without updating records}';

    protected $description =
        'Backfill province, municipality, and barangay reference IDs for existing projects and project drafts.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $this->info(
            $dryRun
            ? 'Running location backfill in DRY-RUN mode.'
            : 'Running location backfill.'
        );

        $this->newLine();

        $projectResults =
            $this->backfillProjects(
                $dryRun
            );

        $this->newLine();

        $draftResults =
            $this->backfillDrafts(
                $dryRun
            );

        $this->newLine();

        $this->table(
            [
                'Record Type',
                'Updated',
                'Already Linked',
                'Unresolved',
            ],
            [
                [
                    'Projects',
                    $projectResults['updated'],
                    $projectResults['already_linked'],
                    $projectResults['unresolved'],
                ],
                [
                    'Project Drafts',
                    $draftResults['updated'],
                    $draftResults['already_linked'],
                    $draftResults['unresolved'],
                ],
            ]
        );

        if ($dryRun) {
            $this->warn(
                'No database records were changed because --dry-run was used.'
            );
        } else {
            $this->info(
                'Location reference backfill completed.'
            );
        }

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Official Projects
    |--------------------------------------------------------------------------
    */

    private function backfillProjects(
        bool $dryRun
    ): array {
        $results = [
            'updated' => 0,
            'already_linked' => 0,
            'unresolved' => 0,
        ];

        $projects = Project::query()
            ->orderBy('id')
            ->get();

        foreach ($projects as $project) {
            if (
                $project->province_id
                && $project->municipality_id
                && $project->barangay_id
            ) {
                $results['already_linked']++;

                continue;
            }

            $location = $this->resolveLocation(
                provinceName: $project->province,
                municipalityName: $project->municipality,
                barangayName: $project->barangay,
            );

            if (!$location) {
                $results['unresolved']++;

                $this->warn(
                    sprintf(
                        'Project #%d unresolved: %s / %s / %s',
                        $project->id,
                        $project->province ?: '[empty]',
                        $project->municipality ?: '[empty]',
                        $project->barangay ?: '[empty]',
                    )
                );

                continue;
            }

            $this->line(
                sprintf(
                    'Project #%d: %s → IDs [%d, %d, %d]',
                    $project->id,
                    $project->project_title,
                    $location['province']->id,
                    $location['municipality']->id,
                    $location['barangay']->id,
                )
            );

            if (!$dryRun) {
                $project->updateQuietly([
                    'province_id' =>
                        $location['province']->id,

                    'municipality_id' =>
                        $location['municipality']->id,

                    'barangay_id' =>
                        $location['barangay']->id,
                ]);
            }

            $results['updated']++;
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | GIP Project Drafts
    |--------------------------------------------------------------------------
    */

    private function backfillDrafts(
        bool $dryRun
    ): array {
        $results = [
            'updated' => 0,
            'already_linked' => 0,
            'unresolved' => 0,
        ];

        $drafts = ProjectDraft::query()
            ->orderBy('id')
            ->get();

        foreach ($drafts as $draft) {
            if (
                $draft->province_id
                && $draft->municipality_id
                && $draft->barangay_id
            ) {
                $results['already_linked']++;

                continue;
            }

            $location = $this->resolveLocation(
                provinceName: $draft->province,
                municipalityName: $draft->municipality,
                barangayName: $draft->barangay,
            );

            if (!$location) {
                $results['unresolved']++;

                $this->warn(
                    sprintf(
                        'Draft #%d unresolved: %s / %s / %s',
                        $draft->id,
                        $draft->province ?: '[empty]',
                        $draft->municipality ?: '[empty]',
                        $draft->barangay ?: '[empty]',
                    )
                );

                continue;
            }

            $this->line(
                sprintf(
                    'Draft #%d: %s → IDs [%d, %d, %d]',
                    $draft->id,
                    $draft->project_title,
                    $location['province']->id,
                    $location['municipality']->id,
                    $location['barangay']->id,
                )
            );

            if (!$dryRun) {
                $draft->updateQuietly([
                    'province_id' =>
                        $location['province']->id,

                    'municipality_id' =>
                        $location['municipality']->id,

                    'barangay_id' =>
                        $location['barangay']->id,
                ]);
            }

            $results['updated']++;
        }

        return $results;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Location Hierarchy
    |--------------------------------------------------------------------------
    */

    private function resolveLocation(
        ?string $provinceName,
        ?string $municipalityName,
        ?string $barangayName,
    ): ?array {
        if (
            blank($provinceName)
            || blank($municipalityName)
            || blank($barangayName)
        ) {
            return null;
        }

        $province = Province::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    strtolower(
                        trim($provinceName)
                    ),
                ]
            )
            ->first();

        if (!$province) {
            return null;
        }

        $municipality = Municipality::query()
            ->where(
                'province_id',
                $province->id
            )
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    strtolower(
                        trim($municipalityName)
                    ),
                ]
            )
            ->first();

        if (!$municipality) {
            return null;
        }

        $barangay = Barangay::query()
            ->where(
                'municipality_id',
                $municipality->id
            )
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    strtolower(
                        trim($barangayName)
                    ),
                ]
            )
            ->first();

        if (!$barangay) {
            return null;
        }

        return [
            'province' =>
                $province,

            'municipality' =>
                $municipality,

            'barangay' =>
                $barangay,
        ];
    }
}