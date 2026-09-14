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
        | Insurance Claim (Incident Report)
        |--------------------------------------------------------------------------
        |
        | One row per incident where one or more beneficiaries were injured
        | during project implementation. The time is approximate by nature
        | (as reported), so it is kept nullable and separate from the date.
        |
        */

        Schema::create('project_insurance_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('incident_description');
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['project_id', 'incident_date'],
                'pi_claims_project_incident_date_index',
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Insurance Claim Beneficiaries
        |--------------------------------------------------------------------------
        |
        | Who was injured in a given incident. Optionally linked back to an
        | existing ProjectBeneficiary roster row (auto-filled name), but name
        | and address are always stored here as-reported since a beneficiary's
        | address is not otherwise recorded per-person.
        |
        */

        Schema::create('project_insurance_claim_beneficiaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('claim_id')
                ->constrained('project_insurance_claims')
                ->cascadeOnDelete();
            $table->foreignId('beneficiary_id')
                ->nullable()
                ->constrained('project_beneficiaries')
                ->nullOnDelete();
            $table->string('full_name', 150);
            $table->string('address', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_insurance_claim_beneficiaries');
        Schema::dropIfExists('project_insurance_claims');
    }
};
