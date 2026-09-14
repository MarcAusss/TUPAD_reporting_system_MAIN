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
        | PPE Delivery Items
        |--------------------------------------------------------------------------
        |
        | Which of the project's planned PPE items (project_ppe_items — encoded at
        | project creation) were included in a given delivery receipt, and how many
        | units were delivered. Lets "PPE Provided" be picked from the project's own
        | declared PPE list instead of free text.
        |
        */

        Schema::create('project_ppe_delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ppe_delivery_id')
                ->constrained('project_ppe_deliveries')
                ->cascadeOnDelete();
            $table->foreignId('ppe_item_id')
                ->constrained('project_ppe_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(
                ['ppe_delivery_id', 'ppe_item_id'],
                'ppe_delivery_items_delivery_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ppe_delivery_items');
    }
};
