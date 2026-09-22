<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectCodeSequence;
use App\Models\Province;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds the official TUPAD-RO5 Project Code the moment a project becomes
 * APPROVED. The coordinator never supplies or edits any part of it.
 *
 * Callers must invoke generate() from inside the same DB::transaction()
 * that performs the status transition to APPROVED, so the reserved series
 * number, the approval record, and the status change commit or roll back
 * together.
 */
class ProjectCodeGenerator
{
    public function generate(Project $project, CarbonInterface $approvalDate): string
    {
        $province = $this->resolveProvince($project);
        $municipality = $project->municipalityReference;

        if ($municipality === null) {
            throw new RuntimeException(
                "Project #{$project->id} has no assigned municipality; a Project Code cannot be generated."
            );
        }

        $provinceCode = ProjectCodeMaps::provinceCode($province->name);
        $municipalityCode = ProjectCodeMaps::municipalityCode(
            $province->name,
            $municipality->name,
        );

        $year = (int) $approvalDate->format('Y');
        $month = (int) $approvalDate->format('n');

        $series = $this->nextSeries($province, $year, $month);

        return sprintf(
            'TUPAD-RO5-%s-%s-%s-%s-%s',
            $provinceCode,
            $municipalityCode,
            $approvalDate->format('y'),
            $approvalDate->format('m'),
            str_pad((string) $series, 2, '0', STR_PAD_LEFT),
        );
    }

    private function resolveProvince(Project $project): Province
    {
        $province = $project->creator?->assignedProvince
            ?? $project->provinceReference;

        if ($province === null) {
            throw new RuntimeException(
                "Project #{$project->id} has no resolvable province (the coordinator has no assigned province and the project has no province reference); a Project Code cannot be generated."
            );
        }

        return $province;
    }
    
    private function nextSeries(Province $province, int $year, int $month): int
    {
        $now = now();
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        DB::statement(
            $isSqlite
                ? 'INSERT INTO project_code_sequences
                        (province_id, year, month, last_series, created_at, updated_at)
                    VALUES (?, ?, ?, 1, ?, ?)
                    ON CONFLICT (province_id, year, month)
                    DO UPDATE SET last_series = last_series + 1'
                : 'INSERT INTO project_code_sequences
                        (province_id, year, month, last_series, created_at, updated_at)
                    VALUES (?, ?, ?, 1, ?, ?)
                    ON DUPLICATE KEY UPDATE last_series = last_series + 1',
            [$province->id, $year, $month, $now, $now],
        );

        return (int) ProjectCodeSequence::query()
            ->where('province_id', $province->id)
            ->where('year', $year)
            ->where('month', $month)
            ->value('last_series');
    }
}
