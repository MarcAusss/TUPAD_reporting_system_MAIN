<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $table->foreignId('municipality_id')->constrained('municipalities')->restrictOnDelete();
            $table->string('district', 100);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'municipality_id']);
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_location_barangay', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_location_id')->constrained('project_locations')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_location_id', 'barangay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_location_barangay');
        Schema::dropIfExists('project_locations');
    }
};
