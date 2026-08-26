<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_draft_ppe_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_draft_id')
                ->constrained('project_drafts')
                ->cascadeOnDelete();

            $table->string('ppe_type', 30);

            $table->string('product', 255);

            $table->unsignedInteger('beneficiary_count');

            $table->decimal('unit_amount', 12, 2);

            $table->decimal('total_amount', 15, 2);

            $table->timestamps();

            $table->index([
                'project_draft_id',
                'ppe_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_draft_ppe_items');
    }
};