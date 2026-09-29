<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_barangay_ppe_item_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained('barangays')->restrictOnDelete();
            $table->foreignId('project_ppe_item_id')->constrained('project_ppe_items')->cascadeOnDelete();
            $table->unsignedInteger('recipients');
            $table->timestamps();

            $table->unique(
                ['project_id', 'barangay_id', 'project_ppe_item_id'],
                'project_brgy_ppe_item_count_scope_unique'
            );  
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_barangay_ppe_item_counts');
    }
};
