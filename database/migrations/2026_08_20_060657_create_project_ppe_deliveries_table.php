<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_ppe_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->date('delivery_receipt_date');

            $table->text('ppe_provided');

            /*
            |--------------------------------------------------------------------------
            | Inventory Integration Placeholder
            |--------------------------------------------------------------------------
            |
            | Phase 8 may populate this with the external PPE Inventory
            | transaction/reference identifier.
            |
            */

            $table->string('inventory_reference', 150)
                ->nullable();

            $table->text('remarks')->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ppe_deliveries');
    }
};