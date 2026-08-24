<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectStatusHistorySeeder extends Seeder
{
    public function run(): void
    {
        Project::query()
            ->get()
            ->each(function (Project $project): void {
                if (
                    $project->statusHistory()
                        ->exists()
                ) {
                    return;
                }

                $project->statusHistory()->create([
                    'from_status' => null,

                    'to_status' =>
                        $project->status->value,

                    'changed_by' =>
                        $project->updated_by
                        ?? $project->created_by,

                    'remarks' =>
                        'Initial status history generated from existing project data.',

                    'changed_at' =>
                        $project->updated_at
                        ?? $project->created_at
                        ?? now(),
                ]);
            });
    }
}