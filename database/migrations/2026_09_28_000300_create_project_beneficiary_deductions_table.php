<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the Focal completes the obligation tranches with fewer
     * beneficiaries than the project declared, the TUPAD Coordinator records
     * which beneficiary addresses the excluded beneficiaries came from.
     *
     * Actual Beneficiary Mapping = Beneficiary Mapping (project_beneficiary_addresses)
     *                              − these per-address deductions.
     */
    public function up(): void
    {
        // An earlier MySQL run could leave this (empty) table behind: MySQL
        // does not roll back DDL when a later statement in the migration fails.
        Schema::dropIfExists('project_beneficiary_deductions');

        Schema::create('project_beneficiary_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_beneficiary_address_id');
            // Explicit name: the generated one exceeds MySQL's 64-character limit.
            $table->foreign('project_beneficiary_address_id', 'pbd_beneficiary_address_id_foreign')
                ->references('id')
                ->on('project_beneficiary_addresses')
                ->cascadeOnDelete();
            $table->unsignedInteger('beneficiaries_deducted')->default(0);
            $table->unsignedInteger('female_deducted')->default(0);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique('project_beneficiary_address_id', 'project_beneficiary_deductions_address_unique');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('beneficiary_deductions_recorded_at')->nullable()->after('obligations_completed_by');
            $table->foreignId('beneficiary_deductions_recorded_by')
                ->nullable()
                ->after('beneficiary_deductions_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beneficiary_deductions_recorded_by');
            $table->dropColumn('beneficiary_deductions_recorded_at');
        });

        Schema::dropIfExists('project_beneficiary_deductions');
    }
};
