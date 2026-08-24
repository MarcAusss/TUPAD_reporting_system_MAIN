<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write(
            model: $model,
            action: 'created',
            oldValues: null,
            newValues: $model->getAttributes(),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        if (empty($changes)) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $model->getOriginal($key);
        }

        $this->write(
            model: $model,
            action: 'updated',
            oldValues: $oldValues,
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $this->write(
            model: $model,
            action: 'deleted',
            oldValues: $model->getAttributes(),
            newValues: null,
        );
    }

    private function write(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        if (
            !Schema::hasTable('audit_logs')
            || $model instanceof AuditLog
        ) {
            return;
        }

        $hiddenFields = [
            'password',
            'remember_token',
        ];

        $oldValues = $oldValues
            ? Arr::except(
                $oldValues,
                $hiddenFields
            )
            : null;

        $newValues = $newValues
            ? Arr::except(
                $newValues,
                $hiddenFields
            )
            : null;

        AuditLog::create([
            'user_id' =>
                auth()->id(),

            'action' =>
                $action,

            'module' =>
                $this->moduleName($model),

            'auditable_type' =>
                    $model::class,

            'auditable_id' =>
                $model->getKey(),

            'old_values' =>
                $oldValues,

            'new_values' =>
                $newValues,

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),

            'performed_at' =>
                now(),
        ]);
    }

    private function moduleName(Model $model): string
    {
        return match (true) {
            $model instanceof \App\Models\Adl =>
            'ADL Management',

            $model instanceof \App\Models\AdlAllocation =>
            'ADL Allocation',

            $model instanceof \App\Models\AdlRealignment =>
            'ADL Re-alignment',

            $model instanceof \App\Models\Project =>
            'Project Management',

            $model instanceof \App\Models\ProjectDraft =>
            'GIP Project Draft',

            $model instanceof \App\Models\ProjectEvaluation =>
            'Project Evaluation',

            $model instanceof \App\Models\ProjectApproval =>
            'Project Approval',

            $model instanceof \App\Models\ProjectInsuranceEnrollment =>
            'Insurance Enrollment',

            $model instanceof \App\Models\ProjectPpeDelivery =>
            'PPE Delivery',

            $model instanceof \App\Models\ProjectNoticeToProceed =>
            'Notice to Proceed',

            $model instanceof \App\Models\ProjectOrientation =>
            'Orientation',

            $model instanceof \App\Models\ProjectImplementation =>
            'Project Implementation',

            $model instanceof \App\Models\ProjectPostDocument =>
            'Post Documents',

            $model instanceof \App\Models\ProjectObligation =>
            'Payment / Obligation',

            $model instanceof \App\Models\ProjectPayout =>
            'Payout',

            $model instanceof \App\Models\User =>
            'User Management',

            $model instanceof \App\Models\ProjectBeneficiary =>
            'Beneficiary Registry',

            default =>
            class_basename($model),
        };
    }
}