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

        /*
        |--------------------------------------------------------------------------
        | Updated Event State
        |--------------------------------------------------------------------------
        |
        | During Eloquent's "updated" event, original attributes still contain
        | the previous value while getChanges() contains the value just written.
        |
        */

        $oldStatus = $project->getRawOriginal('status');
        $newStatus = $project->getChanges()['status'];

        $project->statusHistory()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' =>
                $project->hasStatusTransitionContext()
                    ? $project->statusTransitionActorId()
                    : auth()->id(),
            'remarks' =>
                $project->statusTransitionRemarks(),
            'changed_at' => now(),
        ]);
    }
}
