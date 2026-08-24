<?php

namespace App\Observers;

use App\Models\Project;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $project->statusHistory()->create([
            'from_status' => null,
            'to_status' => $project->status->value,
            'changed_by' => auth()->id(),
            'remarks' => 'Project record created.',
            'changed_at' => now(),
        ]);
    }

    public function updated(Project $project): void
    {
        if (!$project->wasChanged('status')) {
            return;
        }

        $oldStatus = $project->getOriginal('status');
        $newStatus = $project->getRawOriginal('status');

        $project->statusHistory()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }
}