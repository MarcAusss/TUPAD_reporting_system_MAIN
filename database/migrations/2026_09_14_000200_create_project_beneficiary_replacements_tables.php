<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Beneficiary Replacement Batch
        |--------------------------------------------------------------------------
        |
        | One row per replacement transaction (e.g. "5 beneficiaries replaced").
        | The optional detail-revision flags below are only ever set to true when
        | that specific detail actually changed as part of this transaction — an
        | unrelated detail is left both unflagged and unchanged.
        |
        */

        Schema::create('project_beneficiary_replacements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at');

            $table->boolean('insurance_beneficiaries_changed')->default(false);
            $table->unsignedInteger('insurance_beneficiaries_before')->nullable();
            $table->unsignedInteger('insurance_beneficiaries_after')->nullable();

            $table->boolean('total_project_cost_changed')->default(false);
            $table->decimal('total_project_cost_before', 15, 2)->nullable();
            $table->decimal('total_project_cost_after', 15, 2)->nullable();

            $table->boolean('beneficiary_address_changed')->default(false);

            $table->timestamps();

            $table->index(
                ['project_id', 'performed_at'],
                'pb_replacements_project_performed_index',
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Beneficiary Replacement Members
        |--------------------------------------------------------------------------
        |
        | Which existing ProjectBeneficiary rows left ("removed") or joined
        | ("added") in a given replacement batch. Beneficiaries are never
        | deleted — this table only tags which batch they belong to.
        |
        */

        Schema::create('project_beneficiary_replacement_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('replacement_id')
                ->constrained('project_beneficiary_replacements')
                ->cascadeOnDelete();
            $table->foreignId('beneficiary_id')
                ->constrained('project_beneficiaries')
                ->cascadeOnDelete();
            $table->string('role', 10);
            $table->timestamps();

            $table->unique(
                ['replacement_id', 'beneficiary_id'],
                'pb_replacement_members_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_beneficiary_replacement_members');
        Schema::dropIfExists('project_beneficiary_replacements');
    }
};
