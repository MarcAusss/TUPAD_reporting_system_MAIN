<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per project + beneficiary barangay, holding the hazardous
     * worker headcount for that barangay. Keyed by barangay_id (not by
     * project_beneficiary_addresses.id) because
     * ProjectBeneficiaryAddressService::sync() deletes and recreates every
     * address row on each save, so an address row's own id is not stable
     * across saves.
     */
    public function up(): void
    {
        Schema::create('project_barangay_ppe_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->unsignedInteger('hazardous_workers')->default(0);
            $table->unsignedInteger('complete_set_workers')->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['project_id', 'barangay_id'],
                'project_brgy_ppe_profile_project_barangay_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_barangay_ppe_profiles');
    }
};
