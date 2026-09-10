<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_beneficiary_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->foreignId('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->unsignedInteger('beneficiaries_total');
            $table->unsignedInteger('beneficiaries_female')->default(0);
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['project_id', 'barangay_id'],
                'project_ben_addr_project_barangay_unique'
            );
            $table->index(
                ['province_id', 'municipality_id', 'barangay_id'],
                'project_ben_addr_geo_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_beneficiary_addresses');
    }
};
